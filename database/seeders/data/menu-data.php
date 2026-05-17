<?php

/**
 * Demo menu data for two restaurants used by DemoDataSeeder + demo:photos.
 *
 * Each item.slug is also the photo filename (public/photos/<tenant>/<slug>.jpg)
 * and the wiki_article (when present) is what demo:photos looks up on Wikipedia.
 */

return [
    'shwe-food-house' => [
        'name' => 'Shwe Food House',
        'phone' => '+95 9 777 888 999',
        'address' => 'No. 12, Hledan Road, Kamayut Township, Yangon',
        'opening_hours' => '09:00 - 22:00',
        'service_charge_rate' => 5,
        'tax_rate' => 0,
        'currency' => 'MMK',
        'owner' => [
            'name' => 'Ko Aung',
            'email' => 'koaung@shwefoodhouse.mm',
            'phone' => '+95 9 777 888 999',
            'password' => 'password',
        ],
        'tables' => [
            ['number' => 'A1', 'name' => 'Window A1'],
            ['number' => 'A2', 'name' => 'Window A2'],
            ['number' => 'A3', 'name' => 'Window A3'],
            ['number' => 'B1', 'name' => 'Center B1'],
            ['number' => 'B2', 'name' => 'Center B2'],
            ['number' => 'VIP1', 'name' => 'VIP Room'],
        ],
        'categories' => [
            ['key' => 'rice', 'name' => 'Rice & Mains'],
            ['key' => 'noodles', 'name' => 'Noodles'],
            ['key' => 'soups', 'name' => 'Soups & Curries'],
            ['key' => 'salads', 'name' => 'Salads'],
            ['key' => 'snacks', 'name' => 'Snacks'],
            ['key' => 'drinks', 'name' => 'Drinks'],
            ['key' => 'desserts', 'name' => 'Desserts'],
        ],
        'items' => [
            // Rice & Mains
            [
                'slug' => 'chicken-fried-rice',
                'category' => 'rice',
                'name' => 'Chicken Fried Rice',
                'description' => 'Burmese-style fried rice with chicken, egg, scallions, and a touch of soy.',
                'price' => 8500,
                'wiki_article' => 'Fried rice',
            ],
            [
                'slug' => 'coconut-rice-chicken-curry',
                'category' => 'rice',
                'name' => 'Coconut Rice with Chicken Curry',
                'description' => 'Aromatic coconut rice served with slow-cooked yellow chicken curry.',
                'price' => 12000,
                'wiki_article' => 'Burmese curry',
            ],
            [
                'slug' => 'pork-curry-rice',
                'category' => 'rice',
                'name' => 'Wet Pork Curry & Rice',
                'description' => 'Tender braised pork in a rich gravy, served over jasmine rice.',
                'price' => 9500,
                'wiki_article' => 'Burmese curry',
            ],

            // Noodles
            [
                'slug' => 'mohinga',
                'category' => 'noodles',
                'name' => 'Mohinga',
                'description' => 'Myanmar\'s national dish: rice noodles in a savory catfish and lemongrass broth.',
                'price' => 4500,
                'wiki_article' => 'Mohinga',
            ],
            [
                'slug' => 'ohn-no-khao-swe',
                'category' => 'noodles',
                'name' => 'Ohn No Khao Swè',
                'description' => 'Wheat noodles in a creamy coconut chicken curry with crispy noodle topping.',
                'price' => 5500,
                'wiki_article' => 'Ohn no khao swè',
            ],
            [
                'slug' => 'shan-noodle',
                'category' => 'noodles',
                'name' => 'Shan Noodle',
                'description' => 'Sticky rice noodles tossed with marinated chicken or pork in a Shan-style sauce.',
                'price' => 4500,
                'wiki_article' => 'Noodle',
            ],
            [
                'slug' => 'nan-gyi-thoke',
                'category' => 'noodles',
                'name' => 'Nan Gyi Thoke',
                'description' => 'Thick round rice noodles tossed with chicken curry, chickpea flour, and herbs.',
                'price' => 5000,
                'wiki_article' => 'Nan gyi thohk',
            ],

            // Soups & Curries
            [
                'slug' => 'hincho',
                'category' => 'soups',
                'name' => 'Hincho (Burmese Sour Soup)',
                'description' => 'Light, tangy clear soup with seasonal vegetables.',
                'price' => 3500,
                'wiki_article' => 'Soup',
            ],
            [
                'slug' => 'pe-bok-hin',
                'category' => 'soups',
                'name' => 'Pe Bok Hin (Pigeon Pea Curry)',
                'description' => 'Mild yellow pigeon pea stew with onions, turmeric, and ginger.',
                'price' => 4500,
                'wiki_article' => 'Dal',
            ],

            // Salads
            [
                'slug' => 'lahpet-thoke',
                'category' => 'salads',
                'name' => 'Lahpet Thoke',
                'description' => 'Fermented tea leaf salad with peanuts, sesame, fried garlic and tomato.',
                'price' => 5500,
                'wiki_article' => 'Lahpet',
            ],
            [
                'slug' => 'ginger-salad',
                'category' => 'salads',
                'name' => 'Gyin Thoke (Ginger Salad)',
                'description' => 'Pickled ginger salad with fried beans, sesame, and fresh lime.',
                'price' => 4000,
                'wiki_article' => 'Salad',
            ],
            [
                'slug' => 'tomato-salad',
                'category' => 'salads',
                'name' => 'Tomato Salad',
                'description' => 'Burmese tomato salad with crushed peanut, fried onion, and fish sauce.',
                'price' => 3500,
                'wiki_article' => 'Caprese salad',
            ],

            // Snacks
            [
                'slug' => 'samusa-thoke',
                'category' => 'snacks',
                'name' => 'Samusa Thoke',
                'description' => 'Crushed samosas tossed with chickpea curry, mint and tamarind.',
                'price' => 4000,
                'wiki_article' => 'Samosa',
            ],
            [
                'slug' => 'mont-hin-gar-fritters',
                'category' => 'snacks',
                'name' => 'Mohinga with Fritters',
                'description' => 'A bowl of mohinga topped with crispy split-pea fritters.',
                'price' => 5000,
                'wiki_article' => 'Mohinga',
            ],

            // Drinks
            [
                'slug' => 'burmese-milk-tea',
                'category' => 'drinks',
                'name' => 'Burmese Milk Tea',
                'description' => 'Strong black tea with condensed and evaporated milk.',
                'price' => 2000,
                'wiki_article' => 'Burmese milk tea',
            ],
            [
                'slug' => 'lemongrass-cooler',
                'category' => 'drinks',
                'name' => 'Lemongrass Cooler',
                'description' => 'Fresh lemongrass tea served chilled with lime.',
                'price' => 2500,
                'wiki_article' => 'Iced tea',
            ],
            [
                'slug' => 'sugarcane-juice',
                'category' => 'drinks',
                'name' => 'Sugarcane Juice',
                'description' => 'Cold-pressed sugarcane juice with a squeeze of lime.',
                'price' => 1500,
                'wiki_article' => 'Sugarcane juice',
            ],

            // Desserts
            [
                'slug' => 'shwe-yin-aye',
                'category' => 'desserts',
                'name' => 'Shwe Yin Aye',
                'description' => 'Cool dessert of coconut milk, sago, agar jelly and sticky rice.',
                'price' => 4500,
                'wiki_article' => 'Shwe yin aye',
            ],
            [
                'slug' => 'mont-lone-yay-paw',
                'category' => 'desserts',
                'name' => 'Mont Lone Yay Paw',
                'description' => 'Sticky rice dumplings with palm sugar centers, served in shredded coconut.',
                'price' => 3500,
                'wiki_article' => 'Mont lone yay baw',
            ],
        ],
        'collections' => [
            [
                'name' => 'Popular Items',
                'layout_type' => 'horizontal_cards',
                'display_order' => 1,
                'status' => 'active',
                'items' => ['mohinga', 'shan-noodle', 'lahpet-thoke', 'chicken-fried-rice', 'burmese-milk-tea'],
            ],
            [
                'name' => 'Today Special',
                'layout_type' => 'large_featured_cards',
                'display_order' => 2,
                'status' => 'active',
                'items' => ['ohn-no-khao-swe', 'coconut-rice-chicken-curry'],
            ],
            [
                'name' => 'Chef Recommended',
                'layout_type' => 'grid_cards',
                'display_order' => 3,
                'status' => 'active',
                'items' => ['nan-gyi-thoke', 'lahpet-thoke', 'shwe-yin-aye'],
            ],
        ],
    ],

    'bangkok-kitchen' => [
        'name' => 'Bangkok Kitchen',
        'phone' => '+66 2 555 7777',
        'address' => '88 Sukhumvit Soi 11, Khlong Toei Nuea, Watthana, Bangkok',
        'opening_hours' => '11:00 - 23:00',
        'service_charge_rate' => 7,
        'tax_rate' => 7,
        'currency' => 'THB',
        'owner' => [
            'name' => 'Somchai Charoenkul',
            'email' => 'somchai@bangkokkitchen.th',
            'phone' => '+66 2 555 7777',
            'password' => 'password',
        ],
        'tables' => [
            ['number' => 'T1', 'name' => 'Patio T1'],
            ['number' => 'T2', 'name' => 'Patio T2'],
            ['number' => 'T3', 'name' => 'Center T3'],
            ['number' => 'T4', 'name' => 'Center T4'],
            ['number' => 'T5', 'name' => 'Bar T5'],
            ['number' => 'VIP1', 'name' => 'Private Room'],
        ],
        'categories' => [
            ['key' => 'rice', 'name' => 'Rice & Mains'],
            ['key' => 'noodles', 'name' => 'Noodles'],
            ['key' => 'curries', 'name' => 'Soups & Curries'],
            ['key' => 'salads', 'name' => 'Salads'],
            ['key' => 'snacks', 'name' => 'Snacks'],
            ['key' => 'drinks', 'name' => 'Drinks'],
            ['key' => 'desserts', 'name' => 'Desserts'],
        ],
        'items' => [
            // Rice & Mains
            [
                'slug' => 'pad-krapow-moo',
                'category' => 'rice',
                'name' => 'Pad Krapow Moo',
                'description' => 'Stir-fried minced pork with Thai holy basil, chili and garlic, served over rice with a fried egg.',
                'price' => 180,
                'wiki_article' => 'Phat kaphrao',
            ],
            [
                'slug' => 'khao-mun-gai',
                'category' => 'rice',
                'name' => 'Khao Mun Gai',
                'description' => 'Poached chicken on garlic-ginger rice with a fermented soybean dipping sauce.',
                'price' => 160,
                'wiki_article' => 'Hainanese chicken rice',
            ],
            [
                'slug' => 'khao-pad-sapparod',
                'category' => 'rice',
                'name' => 'Khao Pad Sapparod',
                'description' => 'Pineapple fried rice with cashew, raisin, shrimp and curry powder, served in a pineapple shell.',
                'price' => 220,
                'wiki_article' => 'Khao phat',
            ],
            [
                'slug' => 'khao-soi-gai',
                'category' => 'rice',
                'name' => 'Khao Soi Gai',
                'description' => 'Chiang Mai coconut curry noodle soup with chicken, topped with crispy noodles.',
                'price' => 200,
                'wiki_article' => 'Khao soi',
            ],

            // Noodles
            [
                'slug' => 'pad-thai',
                'category' => 'noodles',
                'name' => 'Pad Thai',
                'description' => 'Stir-fried rice noodles with shrimp, egg, tofu, peanut, and tamarind sauce.',
                'price' => 180,
                'wiki_article' => 'Pad thai',
            ],
            [
                'slug' => 'pad-see-ew',
                'category' => 'noodles',
                'name' => 'Pad See Ew',
                'description' => 'Wide rice noodles stir-fried with Chinese broccoli, egg and soy in a smoky wok.',
                'price' => 170,
                'wiki_article' => 'Pad see ew',
            ],
            [
                'slug' => 'boat-noodles',
                'category' => 'noodles',
                'name' => 'Kuay Teow Reua (Boat Noodles)',
                'description' => 'Dark beef noodle soup with morning glory, bean sprouts and chili vinegar.',
                'price' => 160,
                'wiki_article' => 'Kuaitiao ruea',
            ],

            // Soups & Curries
            [
                'slug' => 'tom-yum-goong',
                'category' => 'curries',
                'name' => 'Tom Yum Goong',
                'description' => 'Spicy and sour shrimp soup with lemongrass, kaffir lime, galangal and chili.',
                'price' => 250,
                'wiki_article' => 'Tom yum',
            ],
            [
                'slug' => 'tom-kha-gai',
                'category' => 'curries',
                'name' => 'Tom Kha Gai',
                'description' => 'Chicken coconut milk soup with galangal, lemongrass and lime.',
                'price' => 220,
                'wiki_article' => 'Tom kha kai',
            ],
            [
                'slug' => 'green-curry',
                'category' => 'curries',
                'name' => 'Gaeng Keow Wan Gai',
                'description' => 'Green curry with chicken, Thai eggplant, basil and coconut milk.',
                'price' => 230,
                'wiki_article' => 'Green curry',
            ],
            [
                'slug' => 'massaman-beef',
                'category' => 'curries',
                'name' => 'Massaman Beef Curry',
                'description' => 'Mild, rich curry with slow-braised beef, potato and roasted peanuts.',
                'price' => 280,
                'wiki_article' => 'Massaman curry',
            ],
            [
                'slug' => 'panang-neua',
                'category' => 'curries',
                'name' => 'Panang Neua',
                'description' => 'Thick, peanut-scented red curry with sliced beef and kaffir lime.',
                'price' => 260,
                'wiki_article' => 'Phanaeng curry',
            ],

            // Salads
            [
                'slug' => 'som-tum',
                'category' => 'salads',
                'name' => 'Som Tum',
                'description' => 'Green papaya salad pounded with chili, lime, fish sauce and peanuts.',
                'price' => 140,
                'wiki_article' => 'Som tam',
            ],
            [
                'slug' => 'larb-moo',
                'category' => 'salads',
                'name' => 'Larb Moo',
                'description' => 'Spicy minced pork salad with toasted rice powder, mint and lime.',
                'price' => 160,
                'wiki_article' => 'Larb',
            ],
            [
                'slug' => 'yum-talay',
                'category' => 'salads',
                'name' => 'Yum Talay',
                'description' => 'Spicy seafood salad with shrimp, squid, mussel, lemongrass and chili.',
                'price' => 260,
                'wiki_article' => 'Seafood salad',
            ],

            // Snacks
            [
                'slug' => 'por-pia-tod',
                'category' => 'snacks',
                'name' => 'Por Pia Tod',
                'description' => 'Crispy spring rolls with glass noodle, vegetable and sweet chili dip.',
                'price' => 120,
                'wiki_article' => 'Spring roll',
            ],
            [
                'slug' => 'kanom-jeeb',
                'category' => 'snacks',
                'name' => 'Kanom Jeeb',
                'description' => 'Steamed pork and shrimp dumplings with garlic soy.',
                'price' => 140,
                'wiki_article' => 'Shumai',
            ],

            // Drinks
            [
                'slug' => 'thai-iced-tea',
                'category' => 'drinks',
                'name' => 'Cha Yen (Thai Iced Tea)',
                'description' => 'Sweet black tea with condensed milk over ice.',
                'price' => 80,
                'wiki_article' => 'Thai iced tea',
            ],
            [
                'slug' => 'lemongrass-tea',
                'category' => 'drinks',
                'name' => 'Iced Lemongrass Tea',
                'description' => 'Lightly sweetened lemongrass infusion served chilled.',
                'price' => 80,
                'wiki_article' => 'Iced tea',
            ],
            [
                'slug' => 'coconut-water',
                'category' => 'drinks',
                'name' => 'Fresh Coconut Water',
                'description' => 'Young Thai coconut, served whole.',
                'price' => 100,
                'wiki_article' => 'Coconut water',
            ],

            // Desserts
            [
                'slug' => 'mango-sticky-rice',
                'category' => 'desserts',
                'name' => 'Khao Niao Mamuang',
                'description' => 'Sweet coconut sticky rice with ripe mango slices.',
                'price' => 150,
                'wiki_article' => 'Mango sticky rice',
            ],
            [
                'slug' => 'tub-tim-krob',
                'category' => 'desserts',
                'name' => 'Tub Tim Krob',
                'description' => 'Water chestnut rubies in sweet coconut milk over crushed ice.',
                'price' => 110,
                'wiki_article' => 'Thapthim krop',
            ],
        ],
        'collections' => [
            [
                'name' => 'Popular Items',
                'layout_type' => 'horizontal_cards',
                'display_order' => 1,
                'status' => 'active',
                'items' => ['pad-thai', 'tom-yum-goong', 'green-curry', 'som-tum', 'thai-iced-tea'],
            ],
            [
                'name' => 'Today Special',
                'layout_type' => 'large_featured_cards',
                'display_order' => 2,
                'status' => 'active',
                'items' => ['massaman-beef', 'khao-soi-gai'],
            ],
            [
                'name' => 'Chef Recommended',
                'layout_type' => 'grid_cards',
                'display_order' => 3,
                'status' => 'active',
                'items' => ['panang-neua', 'larb-moo', 'mango-sticky-rice', 'pad-krapow-moo'],
            ],
        ],
    ],
];
