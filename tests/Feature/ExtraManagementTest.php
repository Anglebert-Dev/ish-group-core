<?php

namespace Tests\Feature;

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use App\Models\Extra\Extra;
use Tests\TestCase;

class ExtraManagementTest extends TestCase
{
    public function test_guest_cannot_list_extras(): void
    {
        $this->get(route('extras.index'))->assertRedirect(route('staff.login'));
    }

    public function test_staff_can_crud_an_optional_extra(): void
    {
        $this->actingAsStaff();

        $this->get(route('extras.index'))->assertOk();
        $this->get(route('extras.create'))->assertOk();

        $this->post(route('extras.store'), [
            'name' => 'Child seat',
            'description' => 'Forward-facing child seat',
            'price' => '5000',
            'type' => ExtraType::Optional->value,
            'status' => ExtraStatus::Active->value,
        ])->assertRedirect(route('extras.index'));

        $extra = Extra::query()->where('name', 'Child seat')->first();
        $this->assertNotNull($extra);
        $this->assertTrue($extra->type === ExtraType::Optional);
        $this->assertSame('5000.00', (string) $extra->price);

        $this->get(route('extras.show', $extra))->assertOk()->assertSee('Child seat');
        $this->get(route('extras.edit', $extra))->assertOk();

        $this->put(route('extras.update', $extra), [
            'name' => 'Child seat XL',
            'description' => 'Larger child seat',
            'price' => '7500',
            'type' => ExtraType::Optional->value,
            'status' => ExtraStatus::Inactive->value,
        ])->assertRedirect(route('extras.index'));

        $this->assertDatabaseHas('extras', [
            'id' => $extra->id,
            'name' => 'Child seat XL',
            'status' => ExtraStatus::Inactive->value,
        ]);

        $this->delete(route('extras.destroy', $extra))->assertRedirect(route('extras.index'));
        $this->assertSoftDeleted('extras', ['id' => $extra->id]);
    }

    public function test_migration_seeds_an_active_chauffeur_extra(): void
    {
        $this->assertDatabaseHas('extras', [
            'type' => ExtraType::Chauffeur->value,
            'status' => ExtraStatus::Active->value,
        ]);
    }
}
