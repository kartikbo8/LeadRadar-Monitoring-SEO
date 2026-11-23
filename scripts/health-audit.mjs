import { chromium } from 'playwright';
import { lookup } from 'node:dns/promises';
import net from 'node:net';

const input = JSON.parse(await readStdin());
const host = String(input.host).toLowerCase();
const address = String(input.address);
const urls = Array.isArray(input.urls) ? input.urls : [];
const viewports = [
    { name: 'mobile', width: 390, height: 844, isMobile: true },
    { name: 'tablet', width: 768, height: 1024, isMobile: true },
    { name: 'desktop', width: 1440, height: 900, isMobile: false },
];
const MAX_PAGES = 25;
const MAX_EXTERNAL_HOSTS = 40;
const DISCOVERY_PAGES = 5;
const TABS_PER_VIEWPORT = 3;
const HOSTNAME_PATTERN = /^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/;

if (!host || !address || urls.length === 0 || urls.length > MAX_PAGES || !HOSTNAME_PATTERN.test(host)) {
    throw new Error('Invalid audit input.');
}

// Private, loopback, link-local, CGNAT, documentation, multicast and reserved ranges.
const nonPublic = new net.BlockList();
for (const [subnet, prefix] of [
    ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16],
    ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15],
    ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
]) {
    nonPublic.addSubnet(subnet, prefix, 'ipv4');
}
for (const [subnet, prefix] of [
    // Note: no ::ffff:0:0/96 rule here; BlockList applies it to every IPv4 address. Mapped
    // addresses are unwrapped in isPublicIp() and checked against the IPv4 rules instead.
    ['::', 127], ['64:ff9b::', 96], ['100::', 64], ['2001:db8::', 32],
    ['fc00::', 7], ['fe80::', 10], ['ff00::', 8],
]) {
    nonPublic.addSubnet(subnet, prefix, 'ipv6');
}

function isPublicIp(ip) {
    const mapped = /^::ffff:(\d+\.\d+\.\d+\.\d+)$/i.exec(ip);
    if (mapped) {
        ip = mapped[1];
    }
    const family = net.isIP(ip);
    return family !== 0 && !nonPublic.check(ip, family === 6 ? 'ipv6' : 'ipv4');
}

/** Resolve a third-party host once; only hosts whose every address is public may be loaded. */
async function resolvePublicHost(hostname) {
    if (!HOSTNAME_PATTERN.test(hostname)) {
        return null;
    }

    try {
        const records = await lookup(hostname, { all: true });

        if (records.length === 0 || !records.every((record) => isPublicIp(record.address))) {
            return null;
        }

        return (records.find((record) => record.family === 4) ?? records[0]).address;
    } catch {
        return null;
    }
}

function mapRule(hostname, ip) {
    return `MAP ${hostname} ${ip.includes(':') ? `[${ip}]` : ip}`;
}

let blockedExternalRequests = 0;
const externalHostTypes = new Map();
const offsiteNavigations = new Map();

/**
 * Load the pages once. Every DNS lookup is pinned by --host-resolver-rules, so the browser can
 * only reach the monitored host and third-party hosts that were verified as public above.
 */
async function runPass({ allowedHosts, passUrls, passViewports, discovered, navigationTimeout, settleMs, collect }) {
    const rules = [mapRule(host, address)];
    for (const [hostname, ip] of allowedHosts) {
        rules.push(mapRule(hostname, ip));
    }
    rules.push('MAP * ~NOTFOUND');

    const browser = await chromium.launch({
        headless: true,
        args: [
            `--host-resolver-rules=${rules.join(', ')}`,
            '--force-webrtc-ip-handling-policy=disable_non_proxied_udp',
        ],
    });
    const pages = [];

    try {
        // Viewports run side by side, each with a small pool of tabs, to keep 25 pages × 3 sizes quick.
        await Promise.all(passViewports.map(async (viewport) => {
            const context = await browser.newContext({
                viewport: { width: viewport.width, height: viewport.height },
                isMobile: viewport.isMobile,
                hasTouch: viewport.isMobile,
                serviceWorkers: 'block',
                acceptDownloads: false,
            });
            const queue = [...passUrls];

            await Promise.all(Array.from({ length: Math.min(TABS_PER_VIEWPORT, queue.length) }, async () => {
            while (queue.length > 0) {
                const url = queue.shift();
                const page = await context.newPage();
                const consoleErrors = [];
                const pageErrors = [];
                const failedRequests = [];
                const brokenResources = [];
                const abortedUrls = new Set();

                await page.route('**/*', async (route) => {
                    const request = route.request();
                    let requestUrl;

                    try {
                        requestUrl = new URL(request.url());
                    } catch {
                        await route.abort();
                        return;
                    }

                    const hostname = requestUrl.hostname.toLowerCase();
                    const standardPort = !requestUrl.port || ['80', '443'].includes(requestUrl.port);
                    const isHttp = ['http:', 'https:'].includes(requestUrl.protocol);

                    if (isHttp && standardPort && hostname === host) {
                        await route.continue();
                        return;
                    }

                    if (request.isNavigationRequest()) {
                        if (collect && request.frame() === page.mainFrame() && isHttp) {
                            offsiteNavigations.set(hostname, { from: url, to: requestUrl.href.slice(0, 300) });
                        }
                    } else if (isHttp && standardPort && ['GET', 'HEAD'].includes(request.method())) {
                        if (allowedHosts.has(hostname)) {
                            if (collect) {
                                const types = externalHostTypes.get(hostname) ?? new Set();
                                types.add(request.resourceType());
                                externalHostTypes.set(hostname, types);
                            }
                            await route.continue();
                            return;
                        }

                        discovered?.add(hostname);
                    }

                    blockedExternalRequests += collect ? 1 : 0;
                    abortedUrls.add(request.url());
                    await route.abort();
                });

                if (collect) {
                    page.on('console', (message) => {
                        const text = message.text();
                        // Resource failures are reported with their URL via the response/requestfailed handlers.
                        if (message.type() === 'error' && !text.startsWith('Failed to load resource') && consoleErrors.length < 10) {
                            consoleErrors.push(text.slice(0, 300));
                        }
                    });
                    page.on('pageerror', (error) => {
                        if (pageErrors.length < 10) {
                            pageErrors.push(error.message.slice(0, 300));
                        }
                    });
                    page.on('response', (response) => {
                        if (response.status() >= 400 && brokenResources.length < 20) {
                            const request = response.request();
                            if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
                                return;
                            }
                            brokenResources.push({
                                url: response.url().slice(0, 500),
                                type: request.resourceType(),
                                status: response.status(),
                                error: null,
                            });
                        }
                    });
                    page.on('requestfailed', (request) => {
                        if (abortedUrls.has(request.url())) {
                            return;
                        }
                        // Redirects to hosts outside the pinned allow-list fail with NAME_NOT_RESOLVED by
                        // design; that is the scanner's sandbox, not a fault on the website.
                        try {
                            const failedHost = new URL(request.url()).hostname.toLowerCase();
                            if (failedHost !== host && !allowedHosts.has(failedHost)) {
                                return;
                            }
                        } catch {
                            return;
                        }
                        const error = request.failure()?.errorText ?? 'Request failed';
                        if (brokenResources.length < 20) {
                            brokenResources.push({
                                url: request.url().slice(0, 500),
                                type: request.resourceType(),
                                status: null,
                                error: error.slice(0, 200),
                            });
                        }
                        try {
                            if (new URL(request.url()).hostname.toLowerCase() === host && failedRequests.length < 10) {
                                failedRequests.push(request.url().slice(0, 500));
                            }
                        } catch {
                            // Ignore unparsable URLs.
                        }
                    });
                }

                let responseStatus = null;
                let navigationError = null;

                try {
                    const response = await page.goto(url, { waitUntil: 'load', timeout: navigationTimeout });
                    responseStatus = response?.status() ?? null;
                } catch (error) {
                    // A load timeout after the document arrived (slow third-party assets) is not a page failure.
                    if (!String(error.message).includes('Timeout') || page.url() === 'about:blank') {
                        navigationError = String(error.message).split('\n')[0].slice(0, 300);
                    }
                }

                await page.waitForTimeout(settleMs);

                if (collect) {
                    const dimensions = await page.evaluate(() => {
                        const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
                        const documentWidth = Math.max(
                            document.documentElement?.scrollWidth ?? 0,
                            document.body?.scrollWidth ?? 0,
                        );
                        const describe = (element) => {
                            let label = element.tagName.toLowerCase();
                            if (element.id) {
                                label += `#${element.id}`;
                            }
                            const classes = [...element.classList].slice(0, 3);
                            if (classes.length > 0) {
                                label += `.${classes.join('.')}`;
                            }
                            return label.slice(0, 120);
                        };
                        const overflowElements = [];

                        if (documentWidth > viewportWidth + 1 && document.body) {
                            for (const element of document.body.querySelectorAll('*')) {
                                const rect = element.getBoundingClientRect();
                                const parentRect = element.parentElement?.getBoundingClientRect();

                                if (rect.width > 0 && rect.right > viewportWidth + 1
                                    && (!parentRect || parentRect.right <= viewportWidth + 1)) {
                                    overflowElements.push({ element: describe(element), right: Math.round(rect.right) });
                                    if (overflowElements.length === 5) {
                                        break;
                                    }
                                }
                            }
                        }

                        return {
                            viewportWidth,
                            documentWidth,
                            overflowElements,
                            title: document.title.slice(0, 300),
                        };
                    }).catch(() => ({
                        viewportWidth: viewport.width,
                        documentWidth: viewport.width,
                        overflowElements: [],
                        title: '',
                    }));

                    pages.push({
                        url,
                        viewport: viewport.name,
                        response_status: responseStatus,
                        navigation_error: navigationError,
                        title: dimensions.title,
                        viewport_width: dimensions.viewportWidth,
                        document_width: dimensions.documentWidth,
                        horizontal_overflow: dimensions.documentWidth > dimensions.viewportWidth + 1,
                        overflow_elements: dimensions.overflowElements,
                        console_errors: consoleErrors,
                        page_errors: pageErrors,
                        failed_requests: failedRequests,
                        broken_resources: brokenResources,
                    });
                }

                await page.close();
            }
            }));

            await context.close();
        }));
    } finally {
        await browser.close();
    }

    // Parallel tabs finish out of order; report pages in crawl order, then mobile → tablet → desktop.
    const deviceOrder = viewports.map((viewport) => viewport.name);
    return pages.sort((a, b) => urls.indexOf(a.url) - urls.indexOf(b.url)
        || deviceOrder.indexOf(a.viewport) - deviceOrder.indexOf(b.viewport));
}

// Discover which third-party hosts the pages load (two rounds, so hosts requested by
// third-party scripts such as font loaders are found too), then verify each is public.
const allowedHosts = new Map();
const rejectedHosts = new Set();
const desktop = viewports.filter((viewport) => viewport.name === 'desktop');

for (let round = 0; round < 2; round++) {
    const discovered = new Set();
    // Pages share most third-party hosts, so discovery samples the first few to stay fast.
    await runPass({ allowedHosts, passUrls: urls.slice(0, DISCOVERY_PAGES), passViewports: desktop, discovered, navigationTimeout: 10000, settleMs: 500, collect: false });

    const candidates = [...discovered].filter((hostname) => !allowedHosts.has(hostname) && !rejectedHosts.has(hostname));
    if (candidates.length === 0) {
        break;
    }

    for (const hostname of candidates) {
        if (allowedHosts.size >= MAX_EXTERNAL_HOSTS) {
            break;
        }
        const ip = await resolvePublicHost(hostname);
        ip ? allowedHosts.set(hostname, ip) : rejectedHosts.add(hostname);
    }
}

const pages = await runPass({ allowedHosts, passUrls: urls, passViewports: viewports, discovered: null, navigationTimeout: 15000, settleMs: 1000, collect: true });

process.stdout.write(JSON.stringify({
    pages,
    blocked_external_requests: blockedExternalRequests,
    external_hosts: [...externalHostTypes].map(([hostname, types]) => ({ host: hostname, types: [...types].sort() })),
    offsite_navigations: [...offsiteNavigations.values()],
}));

async function readStdin() {
    let input = '';

    for await (const chunk of process.stdin) {
        input += chunk;
    }

    return input;
}
