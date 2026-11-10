<?php

namespace Tests\Feature\Categories;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CategoryTreePage;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Catalog\CatalogTestHelpers;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->seed([CategoryTreeSeeder::class, AdminUserSeeder::class]);
        $this->actingAs(User::query()->where('email', 'admin@skydrop.retail')->firstOrFail());
    }

    public function test_tree_manager_pages_render(): void
    {
        $this->get(CategoryResource::getUrl('index'))->assertOk()->assertSee('Mobiles &amp; Electronics', false);
        $this->get(CategoryResource::getUrl('tree'))->assertOk()->assertSee('fashion/men/clothing/t-shirts-polos');
        $this->get(CategoryResource::getUrl('unmatched'))->assertOk();
        $node = Category::query()->where('path', 'fashion/men')->firstOrFail();
        $this->get(CategoryResource::getUrl('edit', ['record' => $node]))->assertOk()->assertSee('Edit Men');
    }

    public function test_tree_page_reorders_siblings_and_edit_page_saves_sources(): void
    {
        $men = Category::query()->where('path', 'fashion/men')->firstOrFail();
        $women = Category::query()->where('path', 'fashion/women')->firstOrFail();
        Livewire::test(CategoryTreePage::class)->call('reorder', [$women->id, $men->id]);
        $this->assertLessThan($men->fresh()->sort_key, $women->fresh()->sort_key);
        $this->assertStringStartsWith($women->fresh()->sort_key.'.', Category::query()->where('path', 'fashion/women/clothing')->value('sort_key'));

        $tea = Category::query()->where('path', 'grocery-gourmet/beverages/tea')->firstOrFail();
        Livewire::test(EditCategory::class, ['record' => $tea->id])
            ->fillForm(['name_bn' => 'চা পাতা', 'sources.myntra.query' => '', 'sources.flipkart.query' => 'assam tea'])
            ->call('save')->assertHasNoFormErrors();
        $tea->refresh();
        $this->assertSame('চা পাতা', $tea->name_bn);
        $this->assertSame(['amazon', 'flipkart'], $tea->stores());
        $this->assertSame('assam tea', $tea->sources['flipkart']['query']);
        $this->assertSame('grocery-gourmet/beverages/tea', $tea->path);
    }
}
