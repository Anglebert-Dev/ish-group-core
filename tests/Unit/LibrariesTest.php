<?php

namespace Tests\Unit;

use App\Libraries\ColorLibrary;
use App\Libraries\GeneralLibrary;
use App\Models\Business\Business;
use App\Models\Role\Role;
use PHPUnit\Framework\TestCase;

class LibrariesTest extends TestCase
{
    public function test_color_library_darkens_and_lightens(): void
    {
        $dark = ColorLibrary::darken('#124EB0');
        $light = ColorLibrary::lighten('#124EB0');

        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $dark);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $light);
        $this->assertNotSame('#124EB0', $dark);
        $this->assertNotSame('#124EB0', $light);
    }

    public function test_general_library_response_shape(): void
    {
        $this->assertSame(
            ['status' => true, 'data' => 1, 'message' => 'ok'],
            GeneralLibrary::response(true, 1, 'ok'),
        );
    }

    public function test_business_branding_fallbacks(): void
    {
        $business = new Business;

        $this->assertSame(Business::DEFAULT_NAME, $business->companyName());
        $this->assertSame(Business::DEFAULT_BRAND_COLOR, $business->brandColor());
        $this->assertSame('/favicon-32.png', $business->faviconUrl());
    }

    public function test_business_module_toggles(): void
    {
        $business = new Business;
        $this->assertTrue($business->hasModuleEnabled('Anything'), 'null list = everything on');

        $business->enabled_modules = ['Frontend'];
        $this->assertTrue($business->hasModuleEnabled('frontend'));
        $this->assertFalse($business->hasModuleEnabled('Inventory'));
    }

    public function test_role_names_are_scoped_and_displayed_without_suffix(): void
    {
        $this->assertSame('Manager#7', Role::scopedName('Manager', 7));
        $this->assertSame('Manager', Role::displayName('Manager#7'));
        $this->assertSame('Legacy', Role::displayName('Legacy'));
    }
}
