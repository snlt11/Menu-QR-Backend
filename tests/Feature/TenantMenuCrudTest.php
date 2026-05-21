<?php

beforeEach(function () {
    $this->tenant = makeDemoShop();
    $this->token = ownerLogin($this);
});

afterEach(function () {
    try {
        if (isset($this->tenant) && $this->tenant) {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        }
    } catch (\Throwable $e) {
    }
});

function authedMenu($test)
{
    return $test->withHeader('Authorization', 'Bearer '.test()->token);
}

test('full menu flow: create category, item, collection, attach, list, detach', function () {
    $cat = authedMenu($this)->postJson('/api/t/shophouse/menu-categories', [
        'name' => 'Rice',
        'sort_order' => 1,
    ])->assertStatus(201);
    $categoryId = $cat['data']['id'];
    expect($cat['data']['slug'])->toBe('rice');

    $item = authedMenu($this)->postJson('/api/t/shophouse/menu-items', [
        'menu_category_id' => $categoryId,
        'name' => 'Chicken Fried Rice',
        'price' => 8500,
    ])->assertStatus(201);
    $itemId = $item['data']['id'];
    expect((float) $item['data']['price'])->toBe(8500.0);
    expect($item['data']['slug'])->toBe('chicken-fried-rice');

    $collection = authedMenu($this)->postJson('/api/t/shophouse/menu-collections', [
        'name' => 'Popular Items',
        'layout_type' => 'horizontal_cards',
        'display_order' => 1,
        'status' => 'active',
    ])->assertStatus(201);
    $collectionId = $collection['data']['id'];

    $pivot = authedMenu($this)->postJson("/api/t/shophouse/menu-collections/{$collectionId}/items", [
        'menu_item_id' => $itemId,
        'sort_order' => 1,
        'is_featured' => true,
    ])->assertStatus(201);
    $pivotId = $pivot['data']['id'];

    authedMenu($this)->postJson("/api/t/shophouse/menu-collections/{$collectionId}/items", [
        'menu_item_id' => $itemId,
    ])->assertStatus(409);

    authedMenu($this)->getJson("/api/t/shophouse/menu-collections/{$collectionId}")
        ->assertOk()
        ->assertJsonPath('data.collection.name', 'Popular Items')
        ->assertJsonPath('data.items.0.menu_item_name', 'Chicken Fried Rice');

    authedMenu($this)->deleteJson("/api/t/shophouse/menu-collections/{$collectionId}/items/{$pivotId}")
        ->assertOk();

    authedMenu($this)->getJson("/api/t/shophouse/menu-collections/{$collectionId}")
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

test('menu-item create requires a real category', function () {
    authedMenu($this)->postJson('/api/t/shophouse/menu-items', [
        'menu_category_id' => '00000000-0000-0000-0000-000000000000',
        'name' => 'Foo',
        'price' => 100,
    ])->assertStatus(422);
});

test('menu-items can be filtered by category', function () {
    $catA = authedMenu($this)->postJson('/api/t/shophouse/menu-categories', ['name' => 'Rice'])->assertStatus(201);
    $catB = authedMenu($this)->postJson('/api/t/shophouse/menu-categories', ['name' => 'Drinks'])->assertStatus(201);

    authedMenu($this)->postJson('/api/t/shophouse/menu-items', [
        'menu_category_id' => $catA['data']['id'], 'name' => 'Fried Rice', 'price' => 8500,
    ])->assertStatus(201);
    authedMenu($this)->postJson('/api/t/shophouse/menu-items', [
        'menu_category_id' => $catB['data']['id'], 'name' => 'Lemon Tea', 'price' => 2500,
    ])->assertStatus(201);

    $list = authedMenu($this)->getJson('/api/t/shophouse/menu-items?category='.$catB['data']['id'])->assertOk();
    expect($list['data'])->toHaveCount(1);
    expect($list['data'][0]['name'])->toBe('Lemon Tea');
});

test('menu-collection rejects unknown layout_type', function () {
    authedMenu($this)->postJson('/api/t/shophouse/menu-collections', [
        'name' => 'Bad',
        'layout_type' => 'spiral',
    ])->assertStatus(422);
});
