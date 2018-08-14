<?php

namespace Rcon\Controller\Component;

use Cake\Controller\Component;
use Cake\Utility\Hash;
use Rcon\Lib\DataBf4;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Class Bf4Component
 *
 * @package App\Controller\Component
 * @property FrostbiteComponent $Frostbite
 */
class Bf4Component extends Component {

    public $components = [
        'Rcon.Frostbite',
    ];

    private $_serverInfoMapping = [
        0 => ['status', 'string'],
        1 => ['serverName', 'string'],
        2 => ['currentPlayercount', 'int'],
        3 => ['effectiveMaxPlayercount', 'int'],
        4 => ['currentGamemode', 'string'],
        5 => ['currentMap', 'string'],
        6 => ['roundsPlayed', 'int'],
        7 => ['roundsTotal', 'int'],
        8 => ['scoreEntries', 'int'],
        9 => ['scoreTeam1', 'float'],
        10 => ['scoreTeam2', 'float'],
        11 => ['scoreTeam3', 'float'],
        12 => ['scoreTeam4', 'float'],
        13 => ['scoreTarget', 'int'],
        14 => ['onlineState', 'string'],
        15 => ['ranked', 'bool'],
        16 => ['punkBuster', 'bool'],
        17 => ['hasGamePassword', 'bool'],
        18 => ['serverUpTime', 'int'], # seconds
        19 => ['roundTime', 'int'], # seconds
        20 => ['gameIpAndPort', 'string'],
        21 => ['punkBusterVersion', 'string'],
        22 => ['joinQueueEnabled', 'bool'],
        23 => ['region', 'string'],
        24 => ['closestPingSite', 'string'],
        25 => ['country', 'string'],
        26 => ['blazePlayerCount', 'int'],
        27 => ['blazeGameState', 'string'],
    ];

    /**
     * Connect to the BF4 Rcon server
     *
     * @param string $server   IP address for the server
     * @param int    $port     Query/Admin port for the server
     * @param null   $password (optional) If provided a login attempt will be made
     */
    public function connect($server, $port, $password = null) {

        # Connect
        $this->Frostbite->connect($server, $port);

        # Login
        if ($password) {
            $this->Frostbite->login($password);
        }

    }

    /**
     * Disconnect from the BF4 Rcon server
     */
    public function disconnect() {
        $this->Frostbite->disconnect();
    }

    /**
     * Get server info for the BF4 Rcon server
     *
     * @return array|false Array containing server info on success, false on faillure
     */
    public function info() {

        $response = $this->Frostbite->query('serverInfo');

        if (Hash::get($response, 0) !== 'OK') {
            return false;
        }

        $mapping = $this->_serverInfoMapping;
        for ($i = 0; $i < (4 - $response[8]); $i++) {
            unset($mapping[12 - $i]);
        }
        $mapping = array_values($mapping);

        $return = [];
        foreach ($mapping as $key => $structure) {

            list($field, $format) = $structure;

            if ($field === 'unknown') {
                continue;
            }

            $value = Hash::get($response, $key);

            switch ($format) {
                case 'int':
                    $value = (int) $value;
                    break;
                case 'bool':
                    $value = $this->Frostbite->boolean($value);
                    break;
                case 'float':
                    $value = ceil($value);
                    break;
            }

            $return[$field] = $value;

        }

        # Mode text
        $mode = Hash::get($return, 'currentGamemode');
        $return['currentGamemodeText'] = Hash::get(DataBf4::$modes, $mode);

        # Map text, expansion pack
        $map = Hash::get($return, 'currentMap');
        $return['currentMapText'] = Hash::get(DataBf4::$maps, $map . '.name');
        $return['expansion'] = Hash::get(DataBf4::$maps, $map . '.expansion');

        # Images
        $return['images'] = [
            'map_large' => '/rcon/img/bf4/maps/large/' . strtolower($map) . '.jpg',
            'map_medium' => '/rcon/img/bf4/maps/medium/' . strtolower($map) . '.jpg',
            'map_wide' => '/rcon/img/bf4/maps/wide/' . strtolower($map) . '.jpg',
        ];

        # Team names
        $return['teams'] = [
            0 => 'Joining',
        ];
        $teams = [];
        if ($mode) {
            $teams = Hash::get(DataBf4::$teams, $mode, []);
        }
        if ($map && ! $teams) {
            $teams = Hash::get(DataBf4::$maps, $map . '.teams', []);
        }
        foreach ($teams as $n => $team) {
            $return['teams'][$n] = $team;
        }

        return $return;

        $mode = Hash::get($response, 4);
        $map = Hash::get($response, 5);

        return [
            'status' => Hash::get($response, 0),
            'serverName' => Hash::get($response, 1),
            'currentPlayercount' => (int) Hash::get($response, 2),
            'effectiveMaxPlayercount' => (int) Hash::get($response, 3),
            'currentGamemode' => $mode,
            'currentGamemodeText' => Hash::get(DataBf4::$modes, $mode),
            'currentMap' => $map,
            'currentMapText' => Hash::get(DataBf4::$maps, $map . '.name'),
            'expansion' => Hash::get(DataBf4::$maps, $map . '.expansion'),
            'roundsPlayed' => (int) Hash::get($response, 6),
            'roundsTotal' => (int) Hash::get($response, 7),
            'ranked' => $this->Frostbite->boolean(Hash::get($response, 13)),
            'punkBuster' => $this->Frostbite->boolean(Hash::get($response, 14)),
            'hasGamePassword' => $this->Frostbite->boolean(Hash::get($response, 15)),
            'serverUpTime' => (int) Hash::get($response, 16),
            'roundTime' => (int) Hash::get($response, 17),
            'gameIpAndPort' => Hash::get($response, 18),
            'punkBusterVersion' => Hash::get($response, 19),
            'joinQueueEnabled' => $this->Frostbite->boolean(Hash::get($response, 20)),
            'region' => Hash::get($response, 21),
            'closestPingSite' => Hash::get($response, 22),
            'country' => Hash::get($response, 23),
            'blazePlayerCount' => (int) Hash::get($response, 24),
            'blazeGameState' => Hash::get($response, 25),
            'images' => [
                'map_large' => '/rcon/img/bf4/maps_large/' . strtolower($map) . '.jpg',
                'map_medium' => '/rcon/img/bf4/maps_medium/' . strtolower($map) . '.jpg',
                'map_wide' => '/rcon/img/bf4/maps_wide/' . strtolower($map) . '.jpg',
            ],
        ];

    }

    /**
     * Get list of connected players for the BF4 Rcon server
     *
     * @return array|false Array containing all connected players, false on faillure
     */
    public function players() {

        $response = $this->Frostbite->query('listPlayers all');

        if (Hash::get($response, 0) !== 'OK') {
            return false;
        }

        $playerList = [];
        for ($i = 13; $i < count($response); $i += 10) {
            $playerList[] = [
                'name' => $response[$i],
                'guid' => (int) $response[$i + 1] ?: null,
                'teamId' => (int) $response[$i + 2] ?: null,
                'squadId' => (int) $response[$i + 3] ?: null,
                'kills' => (int) $response[$i + 4],
                'deaths' => (int) $response[$i + 5],
                'score' => (int) $response[$i + 6],
                'rank' => (int) $response[$i + 7],
                'ping' => (int) $response[$i + 8],
            ];
        }

        return $playerList;

    }

}
