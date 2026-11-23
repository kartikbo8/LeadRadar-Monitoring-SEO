<?php

namespace App\Http\Controllers;

use App\Exceptions\WebsiteAuditException;
use App\Services\WebsiteAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request, WebsiteAuditService $websiteAuditService): View
    {
        $scanAttempted = $request->query->has('website');
        $website = '';
        $audit = null;
        $auditError = null;

        if ($scanAttempted) {
            $validated = $request->validate([
                'website' => ['required', 'string', 'max:2048'],
            ]);

            $website = trim($validated['website']);

            try {
                $audit = $websiteAuditService->audit($website);
            } catch (WebsiteAuditException $exception) {
                $auditError = $exception->getMessage();
            }
        }

        return view('companies.index', compact(
            'scanAttempted',
            'website',
            'audit',
            'auditError',
        ));
    }
}
