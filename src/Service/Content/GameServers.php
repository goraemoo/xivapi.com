<?php

namespace App\Service\Content;

use App\Exception\CompanionMarketServerException;

class GameServers
{
    const MARKET_OFFLINE = [
        1,2,3,4,5,6,9,12,14,17,22,23,26,27,29,30,32,38,39,45,48,49,51,54,55,56,57,58,60,61,62,64
    ];
    
    /**
     * It is important new servers are added to the end of this list.
     */
    const LIST = [
        'Adamantoise',  # 0
        'Aegis',        # 1
        'Alexander',    # 2
        'Anima',        # 3
        'Asura',        # 4
        'Atomos',       # 5
        'Bahamut',      # 6
        'Balmung',      # 7
        'Behemoth',     # 8
        'Belias',       # 9
        'Brynhildr',    # 10
        'Cactuar',      # 11
        'Carbuncle',    # 12
        'Cerberus',     # 13
        'Chocobo',      # 14
        'Coeurl',       # 15
        'Diabolos',     # 16
        'Durandal',     # 17
        'Excalibur',    # 18
        'Exodus',       # 19
        'Faerie',       # 20
        'Famfrit',      # 21
        'Fenrir',       # 22
        'Garuda',       # 23
        'Gilgamesh',    # 24
        'Goblin',       # 25
        'Gungnir',      # 26
        'Hades',        # 27
        'Hyperion',     # 28
        'Ifrit',        # 29
        'Ixion',        # 30
        'Jenova',       # 31
        'Kujata',       # 32
        'Lamia',        # 33
        'Leviathan',    # 34
        'Lich',         # 35
        'Louisoix',     # 36
        'Malboro',      # 37
        'Mandragora',   # 38
        'Masamune',     # 39
        'Mateus',       # 40
        'Midgardsormr', # 41
        'Moogle',       # 42
        'Odin',         # 43
        'Omega',        # 44
        'Pandaemonium', # 45
        'Phoenix',      # 46
        'Ragnarok',     # 47
        'Ramuh',        # 48
        'Ridill',       # 49
        'Sargatanas',   # 50
        'Shinryu',      # 51
        'Shiva',        # 52
        'Siren',        # 53
        'Tiamat',       # 54
        'Titan',        # 55
        'Tonberry',     # 56
        'Typhon',       # 57
        'Ultima',       # 58
        'Ultros',       # 59
        'Unicorn',      # 60
        'Valefor',      # 61
        'Yojimbo',      # 62
        'Zalera',       # 63
        'Zeromus',      # 64
        'Zodiark',      # 65
        
    ];

    const LIST_DC_REGIONS = [
        'Elemental' => 1,
        'Gaia'      => 1,
        'Mana'      => 1,

        'Aether'    => 2,
        'Primal'    => 2,
        'Crystal'   => 2,

        'Chaos'     => 3,
        'Light'     => 3,
    ];
    
    const LIST_DC = [
        // jp
        'Elemental' => [
            'Aegis',
            'Atomos',
            'Carbuncle',
            'Garuda',
            'Gungnir',
            'Kujata',
            'Ramuh',
            'Tonberry',
            'Typhon',
            'Unicorn'
        ],
        'Gaia' => [
            'Alexander',
            'Bahamut',
            'Durandal',
            'Fenrir',
            'Ifrit',
            'Ridill',
            'Tiamat',
            'Ultima',
            'Valefor',
            'Yojimbo',
            'Zeromus',
        ],
        'Mana' => [
            'Anima',
            'Asura',
            'Belias',
            'Chocobo',
            'Hades',
            'Ixion',
            'Mandragora',
            'Masamune',
            'Pandaemonium',
            'Shinryu',
            'Titan',
        ],
        
        // na
        'Aether' => [
            'Adamantoise',
            'Cactuar',
            'Faerie',
            'Gilgamesh',
            'Jenova',
            'Midgardsormr',
            'Sargatanas',
            'Siren'
        ],
        'Primal' => [
            'Behemoth',
            'Excalibur',
            'Exodus',
            'Famfrit',
            'Hyperion',
            'Lamia',
            'Leviathan',
            'Ultros'
        ],
        'Crystal' => [
            'Balmung',
            'Brynhildr',
            'Coeurl',
            'Diabolos',
            'Goblin',
            'Malboro',
            'Mateus',
            'Zalera'
        ],
        
        // eu
        'Chaos' => [
            'Cerberus',
            'Louisoix',
            'Moogle',
            'Omega',
            'Ragnarok',
        ],
        
        // eu
        'Light' => [
            'Lich',
            'Odin',
            'Phoenix',
            'Shiva',
            'Zodiark'
        ]
    ];
    
    /**
     * Get a server id from a server string
     */
    public static function getServerId(string $server): int
    {
        $index = array_search(ucwords($server), GameServers::LIST);
        
        if ($index === false) {
            throw new CompanionMarketServerException();
        }
        
        return $index;
    }
    
    /**
     * Get the Data Center for
     */
    public static function getDataCenter(string $server): ?string
    {
        foreach (GameServers::LIST_DC as $dc => $servers) {
            if (in_array($server, $servers)) {
                return $dc;
            }
        }
        
        return 'Chaos';
    }
    
    /**
     * Get the data center servers for a specific server
     */
    public static function getDataCenterServers(string $server): ?array
    {
        $dc = self::getDataCenter($server);
        return $dc ? GameServers::LIST_DC[$dc] : null;
    }

    /**
     * Get the data center server ids for a specific server
     */
    public static function getDataCenterServersIds(string $server): ?array
    {
        $servers = self::getDataCenterServers($server);

        foreach ($servers as $i => $server) {
            $servers[$i] = self::getServerId($server);
        }

        return $servers;
    }
}
