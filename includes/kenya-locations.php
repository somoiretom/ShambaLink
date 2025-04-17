<?php
$kenyaCounties = [
    'Mombasa' => [
        'region' => 'Coastal',
        'subcounties' => [
            'Changamwe' => ['Port Reitz', 'Kipevu', 'Airport', 'Miritini', 'Chaani'],
            'Jomvu' => ['Jomvu Kuu', 'Mikindani', 'Miritini'],
            'Kisauni' => ['Mjambere', 'Junda', 'Bamburi', 'Mwakirunge', 'Mtopanga', 'Magogoni'],
            'Likoni' => ['Likoni', 'Diani', 'Timbwani', 'Mtongwe', 'Shika Adabu'],
            'Mvita' => ['Mji wa Kale', 'Tudor', 'Tononoka', 'Shimanzi', 'Majengo'],
            'Nyali' => ['Frere Town', 'Ziwa la Ngombe', 'Mkomani', 'Kongowea', 'Kadzandani']
        ]
    ],
    'Kwale' => [
        'region' => 'Coastal',
        'subcounties' => [
            'Kinango' => ['Kinango', 'Mackinnon Road', 'Chengoni', 'Mwavumbo', 'Kasemeni'],
            'Lungalunga' => ['Pongwe', 'Kikoneni', 'Gombato Bongwe', 'Ukunda'],
            'Matuga' => ['Tiwi', 'Kubo South', 'Mkongani', 'Ndavaya'],
            'Msambweni' => ['Gombato Bongwe', 'Shimba Hills', 'Mwaluphamba', 'Ramisi']
        ]
    ],
    'Nairobi' => [
        'region' => 'Central',
        'subcounties' => [
            'Dagoretti North' => ['Kilimani', 'Kawangware', 'Gatina', 'Kileleshwa', 'Kabiro'],
            'Dagoretti South' => ['Mutu-ini', 'Ngando', 'Riruta', 'Uthiru', 'Waithaka'],
            'Embakasi Central' => ['Kayole North', 'Kayole Central', 'Kayole South', 'Komarock', 'Matopeni']
        ]
    ]
];

$kenyaRegions = [
    'Coastal' => ['Mombasa', 'Kwale'],
    'Central' => ['Nairobi']
];

$kenyaSoilTypes = [
    'Volcanic' => 'High fertility, common in Rift Valley',
    'Black_Cotton' => 'High clay content, expands when wet',
    'Loam' => 'Well-balanced, good for most crops',
    'Sandy' => 'Good drainage, low fertility',
    'Clay' => 'Poor drainage, high fertility when managed',
    'Laterite' => 'Reddish color, common in Western Kenya',
    'Peat' => 'High organic matter, found in wetlands'
];