<?php
/**
 * Flat-file JSON database helper for Scouts Kriko-M website.
 * Provides safe read/write access with advisory file locking (LOCK_EX).
 */

define('DATA_DIR', dirname(__DIR__) . '/data/');
define('UPLOADS_DIR', dirname(__DIR__) . '/uploads/');

// Ensure directories exist
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(UPLOADS_DIR . 'echos/')) {
    mkdir(UPLOADS_DIR . 'echos/', 0755, true);
}
if (!is_dir(UPLOADS_DIR . 'shop/')) {
    mkdir(UPLOADS_DIR . 'shop/', 0755, true);
}

// Create an index.php inside data/ to prevent directory listing or direct entry
if (!file_exists(DATA_DIR . 'index.php')) {
    file_put_contents(DATA_DIR . 'index.php', '<?php header("Location: ../index.php"); exit; ?>');
}

/**
 * Safely read data from a JSON database file.
 */
function read_db($db_name) {
    $file_path = DATA_DIR . $db_name . '.json';
    
    if (!file_exists($file_path)) {
        init_db_defaults($db_name);
    }
    
    $fp = fopen($file_path, 'r');
    if (!$fp) {
        return [];
    }
    
    // Acquire a shared lock (read lock)
    flock($fp, LOCK_SH);
    $size = filesize($file_path);
    $content = $size > 0 ? fread($fp, $size) : '';
    flock($fp, LOCK_UN);
    fclose($fp);
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Safely write data to a JSON database file.
 */
function write_db($db_name, $data) {
    $file_path = DATA_DIR . $db_name . '.json';
    
    $fp = fopen($file_path, 'w');
    if (!$fp) {
        return false;
    }
    
    // Acquire an exclusive lock (write lock)
    if (flock($fp, LOCK_EX)) {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    
    fclose($fp);
    return false;
}

/**
 * Initialize default data for databases if they do not exist yet.
 */
function init_db_defaults($db_name) {
    $file_path = DATA_DIR . $db_name . '.json';
    $defaults = [];
    
    switch ($db_name) {
        case 'settings':
            $defaults = [
                'scouts_year' => '2026-2027',
                'accounts' => [
                    'groepsleiding' => [
                        'role_name' => 'Groepsleiding',
                        'password_hash' => password_hash('KrikoGroep2026!', PASSWORD_DEFAULT)
                    ],
                    'kapoenen' => [
                        'role_name' => 'Kapoenenleiding',
                        'password_hash' => password_hash('KrikoKapoenen2026!', PASSWORD_DEFAULT)
                    ],
                    'welpen' => [
                        'role_name' => 'Welpenleiding',
                        'password_hash' => password_hash('KrikoWelpen2026!', PASSWORD_DEFAULT)
                    ],
                    'jonggivers' => [
                        'role_name' => 'Jonggiverleiding',
                        'password_hash' => password_hash('KrikoJonggivers2026!', PASSWORD_DEFAULT)
                    ],
                    'givers' => [
                        'role_name' => 'Giverleiding',
                        'password_hash' => password_hash('KrikoGivers2026!', PASSWORD_DEFAULT)
                    ]
                ],
                'bank_iban' => 'BE76 1234 5678 9012',
                'bank_bic' => 'KRIKOBE2B',
                'bank_holder' => 'Scouts Kriko-M vzw',
                'contact_email' => 'groepsleiding@kriko-m.be',
                'contact_phone' => '+32 3 776 00 00',
                'contact_address' => 'Industriepark-Noord 33, 9100 Sint-Niklaas',
                'alert_message' => 'Welkom op de gloednieuwe website van Scouts Kriko-M! De inschrijvingen voor het nieuwe scoutsjaar zijn geopend.',
                'alert_active' => true,
                'registration_fee_first' => 50.00,
                'registration_fee_extra' => 45.00,
                'takken' => [
                    'kapoenen' => [
                        'name' => 'Kapoenen',
                        'age_range' => '6 - 8 jaar',
                        'school_year' => '1e & 2e leerjaar',
                        'email' => 'kapoenenleiding@kriko-m.be',
                        'description' => 'Voor de allerjongsten (de kapoenen) is de scouts een gloednieuwe wereld vol fantasie, spel en verwondering. We spelen bosspelen, knutselen erop los, verkleden ons en maken veel plezier. Spelenderwijs leren ze samenwerken, delen en hun grenzen verleggen in een veilige omgeving.',
                        'uniform' => 'De kapoenen dragen nog geen volledig scoutsuniform. Het dragen van onze tweekleurige groepsdas (bordeaux met beige boordje) is wel verplicht en zorgt voor herkenbaarheid. Speelkleren die vuil mogen worden zijn ideaal!',
                        'class' => 'kapoenen',
                        'leaders' => [
                            ['name' => 'Arne Janssens', 'role' => 'Takleider'],
                            ['name' => 'Mathijs Smet', 'role' => 'Leiding'],
                            ['name' => 'Jonas De Backer', 'role' => 'Leiding'],
                            ['name' => 'Stijn Verstraeten', 'role' => 'Leiding']
                        ],
                        'activities' => [
                            'takweekend' => [
                                'title' => 'Kapoenenweekend',
                                'dates' => '17 - 19 April 2026',
                                'reg_open' => '2026-03-01',
                                'reg_close' => '2026-04-05',
                                'price' => 35.00,
                                'active' => true
                            ],
                            'groepsweekend' => [
                                'title' => 'Groepsweekend',
                                'dates' => '13 - 15 Maart 2026',
                                'reg_open' => '2026-01-15',
                                'reg_close' => '2026-03-01',
                                'price' => 45.00,
                                'active' => true
                            ],
                            'kamp' => [
                                'title' => 'Kapoenen Zomerkamp',
                                'dates' => '21 - 28 Juli 2026',
                                'reg_open' => '2026-05-01',
                                'reg_close' => '2026-06-15',
                                'price' => 95.00,
                                'active' => true
                            ]
                        ]
                    ],
                    'welpen' => [
                        'name' => 'Welpen',
                        'age_range' => '8 - 11 jaar',
                        'school_year' => '3e, 4e & 5e leerjaar',
                        'email' => 'welpenleiding@kriko-m.be',
                        'description' => 'De welpen spelen in het thema van het Jungleboek. Samen met Akela, Baloe en Mowgli beleven ze de gekste avonturen in de jungle (het bos!). Ze leren eenvoudige scouts-technieken (zoals een sjorring maken), gaan elk jaar op een spannend weekend en slapen in lokalen tijdens hun grote zomerkamp.',
                        'uniform' => 'Voor welpen is de groepsdas verplicht. We raden ook ten zeerste aan om ons bordeaux Kriko-M T-shirt of de warme bordeaux Kriko-M trui te dragen. Het officiële Hopper scoutshemd en donkere broek/rok mogen, maar zijn pas verplicht vanaf de jonggivers.',
                        'class' => 'welpen',
                        'leaders' => [
                            ['name' => 'Thomas Weyten', 'role' => 'Takleider'],
                            ['name' => 'Lander De Wilde', 'role' => 'Leiding'],
                            ['name' => 'Kobe Peeters', 'role' => 'Leiding'],
                            ['name' => 'Brent Van Damme', 'role' => 'Leiding']
                        ],
                        'activities' => [
                            'takweekend' => [
                                'title' => 'Welpenweekend',
                                'dates' => '17 - 19 April 2026',
                                'reg_open' => '2026-03-01',
                                'reg_close' => '2026-04-05',
                                'price' => 35.00,
                                'active' => true
                            ],
                            'groepsweekend' => [
                                'title' => 'Groepsweekend',
                                'dates' => '13 - 15 Maart 2026',
                                'reg_open' => '2026-01-15',
                                'reg_close' => '2026-03-01',
                                'price' => 45.00,
                                'active' => true
                            ],
                            'kamp' => [
                                'title' => 'Welpen Zomerkamp',
                                'dates' => '21 - 28 Juli 2026',
                                'reg_open' => '2026-05-01',
                                'reg_close' => '2026-06-15',
                                'price' => 95.00,
                                'active' => true
                            ]
                        ]
                    ],
                    'jonggivers' => [
                        'name' => 'Jonggivers',
                        'age_range' => '11 - 14 jaar',
                        'school_year' => '6e leerjaar, 1e & 2e middelbaar',
                        'email' => 'jonggiverleiding@kriko-m.be',
                        'description' => 'Jonggivers houden van actie en avontuur. Ze leren grotere constructies sjorren, navigeren met kaart en kompas, koken hun eigen potje op een houtvuur en slapen voor het eerst in echte scoutstenten op zomerkamp. Ze trekken er ook regelmatig op uit voor een tweedaagse tocht.',
                        'uniform' => 'Vanaf de jonggivers is het volledige scoutsuniform verplicht: het beige hemd (met alle kentekens op de juiste plaats genaaid), de donkere scoutsbroek of scoutsrok, en de tweekleurige Kriko-M groepsdas.',
                        'class' => 'jonggivers',
                        'leaders' => [
                            ['name' => 'Simon Beck', 'role' => 'Takleider'],
                            ['name' => 'Dieter Claeys', 'role' => 'Leiding'],
                            ['name' => 'Jeroen Wuyts', 'role' => 'Leiding'],
                            ['name' => 'Lukas Maes', 'role' => 'Leiding']
                        ],
                        'activities' => [
                            'takweekend' => [
                                'title' => 'Jonggiverweekend',
                                'dates' => '10 - 12 April 2026',
                                'reg_open' => '2026-02-15',
                                'reg_close' => '2026-04-01',
                                'price' => 38.00,
                                'active' => true
                            ],
                            'groepsweekend' => [
                                'title' => 'Groepsweekend',
                                'dates' => '13 - 15 Maart 2026',
                                'reg_open' => '2026-01-15',
                                'reg_close' => '2026-03-01',
                                'price' => 45.00,
                                'active' => true
                            ],
                            'kamp' => [
                                'title' => 'Jonggiver Zomerkamp',
                                'dates' => '15 - 25 Juli 2026',
                                'reg_open' => '2026-04-15',
                                'reg_close' => '2026-06-15',
                                'price' => 145.00,
                                'active' => true
                            ]
                        ]
                    ],
                    'givers' => [
                        'name' => 'Givers',
                        'age_range' => '14 - 17 jaar',
                        'school_year' => '3e, 4e & 5e middelbaar',
                        'email' => 'giverleiding@kriko-m.be',
                        'description' => 'De givers krijgen veel vrijheid en dragen meer verantwoordelijkheid. Ze bedenken vaak hun eigen activiteiten, gaan op uitdagende droppingen midden in de nacht, en organiseren geldacties om een fantastisch buitenlands zomerkamp te financieren.',
                        'uniform' => 'Het volledige scoutsuniform is verplicht: beige hemd, donkere scoutsbroek/rok en de Kriko-M groepsdas. De givers dragen ook vaak met trots their own tak-t-shirt.',
                        'class' => 'givers',
                        'leaders' => [
                            ['name' => 'Ruben De Sutter', 'role' => 'Takleider'],
                            ['name' => 'Mathias Van Hecke', 'role' => 'Leiding'],
                            ['name' => 'Wouter Segers', 'role' => 'Leiding']
                        ],
                        'activities' => [
                            'takweekend' => [
                                'title' => 'Giverweekend',
                                'dates' => '10 - 12 April 2026',
                                'reg_open' => '2026-02-15',
                                'reg_close' => '2026-04-01',
                                'price' => 40.00,
                                'active' => true
                            ],
                            'groepsweekend' => [
                                'title' => 'Groepsweekend',
                                'dates' => '13 - 15 Maart 2026',
                                'reg_open' => '2026-01-15',
                                'reg_close' => '2026-03-01',
                                'price' => 45.00,
                                'active' => true
                            ],
                            'kamp' => [
                                'title' => 'Buitenlands Giverkamp',
                                'dates' => '10 - 25 Juli 2026',
                                'reg_open' => '2026-03-01',
                                'reg_close' => '2026-06-01',
                                'price' => 295.00,
                                'active' => true
                            ]
                        ]
                    ]
                ]
            ];
            break;
            
        case 'echos':
            // Registry of uploaded Kriko Echo planning documents
            $defaults = [
                [
                    'id' => 'echo_1',
                    'title' => 'Kriko Echo Oktober 2026',
                    'month' => '10',
                    'year' => '2026',
                    'tak' => 'kapoenen',
                    'file_name' => 'echo-oktober-kapoenen.pdf',
                    'uploaded_at' => '2026-09-25 18:30:00',
                    'approved' => true
                ],
                [
                    'id' => 'echo_2',
                    'title' => 'Kriko Echo Oktober 2026',
                    'month' => '10',
                    'year' => '2026',
                    'tak' => 'welpen',
                    'file_name' => 'echo-oktober-welpen.pdf',
                    'uploaded_at' => '2026-09-25 18:32:00',
                    'approved' => true
                ]
            ];
            break;
            
        case 'shop':
            // Preload default scouts clothing items
            $defaults = [
                [
                    'id' => 'item_1',
                    'name' => 'Kriko-M T-shirt (Bordeaux)',
                    'price' => 12.00,
                    'description' => 'Het officiële Kriko-M scouts t-shirt van stevig bordeaux katoen met ons logo groot op de rug en klein op de borst. Perfect voor speelzondagen!',
                    'sizes' => ['6 jaar', '8 jaar', '10 jaar', '12 jaar', 'XS', 'S', 'M', 'L', 'XL'],
                    'image' => 'assets/images/shop/tshirt.jpg',
                    'category' => 'kledij',
                    'active' => true
                ],
                [
                    'id' => 'item_2',
                    'name' => 'Kriko-M Trui (Comfortabele Hoodie)',
                    'price' => 28.00,
                    'description' => 'Onze heerlijke, warme bordeaux scouts hoodie met capuchon en buidelzak. Ideaal voor de koudere avonden rond het kampvuur.',
                    'sizes' => ['8 jaar', '10 jaar', '12 jaar', 'XS', 'S', 'M', 'L', 'XL', 'XXL'],
                    'image' => 'assets/images/shop/hoodie.jpg',
                    'category' => 'kledij',
                    'active' => true
                ],
                [
                    'id' => 'item_3',
                    'name' => 'Kriko-M Groepsdas',
                    'price' => 10.00,
                    'description' => 'De officiële tweekleurige groepsdas van Kriko-M (bordeaux met een beige boordje). Verplicht voor alle takken!',
                    'sizes' => ['Eén maat'],
                    'image' => 'assets/images/shop/das.jpg',
                    'category' => 'uniform',
                    'active' => true
                ],
                [
                    'id' => 'item_4',
                    'name' => 'Kriko Jaarkenteken',
                    'price' => 2.00,
                    'description' => 'Het nieuwste jaarkenteken van Scouts en Gidsen Vlaanderen om op je hemd of das te naaien.',
                    'sizes' => ['Standaard'],
                    'image' => 'assets/images/shop/kenteken.jpg',
                    'category' => 'accessoires',
                    'active' => true
                ]
            ];
            break;
            
        case 'orders':
            // Empty list of shop orders
            $defaults = [];
            break;
            
        case 'registrations':
            // Empty list of event registrations
            $defaults = [];
            break;
            
        case 'parents':
            // Empty list of parent accounts
            $defaults = [];
            break;
            
        case 'password_resets':
            // Empty list of active password reset tokens
            $defaults = [];
            break;
            
        case 'calendar':
            // Default upcoming activities on the home page
            $defaults = [
                [
                    'id' => 'cal_1',
                    'title' => 'Startdag Scoutsjaar',
                    'date' => '2026-09-06',
                    'time' => '14:00 - 17:00',
                    'description' => 'De allereerste scoutsvergadering van het nieuwe jaar! Iedereen is welkom om te komen proberen.',
                    'location' => 'Scoutslokalen, Industriepark-Noord'
                ],
                [
                    'id' => 'cal_2',
                    'title' => 'Kriko Dia-avond',
                    'date' => '2026-10-17',
                    'time' => '19:00 - 22:30',
                    'description' => 'Gezellige dia-avond met de leukste foto\'s en filmpjes van de afgelopen zomerkampen.',
                    'location' => 'Parochiecentrum Sint-Niklaas'
                ],
                [
                    'id' => 'cal_3',
                    'title' => 'Jaarlijkse BBQ & Eetdag',
                    'date' => '2026-11-15',
                    'time' => '11:30 - 19:30',
                    'description' => 'Kom smullen op onze heerlijke jaarlijkse eetdag ten voordele van onze lokalen en tenten.',
                    'location' => 'Grote Speelzaal College'
                ]
            ];
            break;
    }
    
    // Write defaults to the file
    $content = json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($file_path, $content);
}
