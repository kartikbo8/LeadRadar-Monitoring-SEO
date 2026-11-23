<?php

test('the home page opens the Search Console dashboard', function () {
    $this->get('/')
        ->assertRedirect(route('search-console.index'));
});
