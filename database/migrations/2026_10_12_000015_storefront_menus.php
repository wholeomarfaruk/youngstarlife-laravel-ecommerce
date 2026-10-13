<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Storefront menus (config/storefront.php → menus registry). Seeded with exactly the links the site had hard-coded,
 * so switching the layout to dynamic menus changes nothing visible. Category links become "category" items when the
 * category exists (survive slug changes), otherwise stay plain links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_menus', function (Blueprint $t) {
            $t->id(); $t->string('location', 40)->unique(); $t->string('name'); $t->string('title')->nullable();   // optional heading (footer)
            $t->timestamps();
        });
        Schema::create('storefront_menu_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_id')->constrained('storefront_menus')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('storefront_menu_items')->cascadeOnDelete();
            $t->string('type', 20);                       // route | category | product | url | whatsapp | phone
            $t->string('label', 120);
            $t->string('value', 500)->nullable();         // route key, URL or phone number
            $t->unsignedBigInteger('ref_id')->nullable(); // category / product id
            $t->string('icon', 80)->nullable();           // Font Awesome classes
            $t->boolean('new_tab')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['menu_id', 'parent_id', 'sort_order']);
        });

        $now = now();
        $menu = fn (string $location, string $name, ?string $title = null) => DB::table('storefront_menus')->insertGetId(['location' => $location, 'name' => $name, 'title' => $title, 'created_at' => $now, 'updated_at' => $now]);
        $items = function (int $menuId, array $rows) use ($now) {
            foreach (array_values($rows) as $i => $r) DB::table('storefront_menu_items')->insert($r + ['menu_id' => $menuId, 'sort_order' => $i, 'value' => null, 'ref_id' => null, 'icon' => null, 'new_tab' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        };
        $category = function (string $label, string $slug) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            return $id ? ['type' => 'category', 'label' => $label, 'ref_id' => $id] : ['type' => 'url', 'label' => $label, 'value' => '/category/'.$slug];
        };
        $contact = [
            ['type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '8801613046803', 'icon' => 'fa-brands fa-whatsapp', 'new_tab' => true],
            ['type' => 'phone', 'label' => 'Call Us +88 01613-046803', 'value' => '+8801613-046803', 'icon' => 'fa-solid fa-phone'],
        ];

        $items($menu('topbar', 'Top bar'), $contact);
        $items($menu('header', 'Main menu'), [
            ['type' => 'route', 'label' => 'Home', 'value' => 'home'],
            ['type' => 'route', 'label' => 'All Products', 'value' => 'shop'],
            $category('Combo Offer', 'combo-offer'),
            $category('Denim 6 Pocket', 'denim-pants'),
            $category('Denim 4 Pocket', 'denim-4-pocket-pants'),
            $category('Cargo', 'cargo-pants'),
            $category('Kids', 'kids'),
        ]);
        $items($menu('footer', 'Footer', 'যেকোনো তথ্যের জন্য আমাদের মেসেজ করুন অথবা কল করুন।'), $contact);
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_menu_items');
        Schema::dropIfExists('storefront_menus');
    }
};
