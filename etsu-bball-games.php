<?php

/**
 * The plugin bootstrap file
 *
 * @link              http://etsuhoops.com
 * @since             1.0.0
 * @package           ETSU_BBall_Games
 *
 * @wordpress-plugin
 * Plugin Name:       ETSU Basketball Games
 * Plugin URI:        http://etsuhoops.com
 * Description:       Displays the current or previous game for ETSU Men's Basketball
 * Version:           1.0.0
 * Author:            Lucas Byerley
 * Author URI:        https://lucasbyerley.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       etsu_bball_games
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class ETSU_Bball_Games{

    protected $use_test;
	protected $api_key;
	protected $api_url;

	public function __construct($atts = array()){
        
        $this->use_test = ( isset($atts['usetest']) ? $atts['usetest'] : false);
		$this->api_key = $atts['apikey'];
		$this->api_url = 'http://api.espn.com/v1/sports/basketball/mens-college-basketball/teams/2193/events/dates/2017?apikey=';
    
        // add the shortcode and enable the do_shortcode function to be executed in the text-widget
		add_shortcode( 'etsu_bball_games', array($this, 'get_etsu_bball_games') );
		add_filter('widget_text','do_shortcode');
		
		// Register style sheet.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		
		// use default timezone if its set
		if (get_option('timezone_string') != '' && get_option('timezone_string') !== false) {
			date_default_timezone_set(get_option('timezone_string'));
		}
		
	}
	
	private function get_games(){
	    
	    $games = $this->get_data();
	    if ($games == null) {
	        
	        $html = '<div class="alert alert-danger">';
            $html .= '<strong>Error retrieving the games.</strong>';
            $html .= '</div>';
            
            return $html;
	        
	    } else {
	        
	        $previous_game = null;
            $current_game = null;
            
            $prev_state = '';
            for ($i=0; $i<=count($games); $i++) {
                
                $game = $games[$i];
                $game_state = $game['competitions'][0]['status']['state'];
                
                // if game is live, current game is live game, previous game is one before it
                if ($game_state == 'in') {
                    $previous_game = $games[$i-1];
                    $current_game = $game;
                    break;
                }
                
                // if no game is live, the previous game is the last completed, and the current game is the next one
                if ($game_state != $prev_state && $i > 0) {
                    $previous_game = $games[$i-1];
                    $current_game = $game;
                    break;
                }
                
                $prev_state = $game_state;
                
            }
            
            $html = '<div class="etsu-bball-games">';
            
            if ($current_game != null) {
                $html .= $this->buildGameHtml($current_game,'current');
            }
            
            if ($previous_game != null) {
                $html .= $this->buildGameHtml($previous_game,'previous');
            }
            
            if ($current_game == null && $previous_game == null && count($games) > 0) {
                // if not games have completed, grab first game
                if ($prev_state == 'pre') {
                    $current_game = $games[0];
                    $html .= $this->buildGameHtml($previous_game,'Current');
                // if all games have completed, grab last game
                } else if ($prev_state == 'post') {
                    $previous_game = $games[count($games)-1];
                    $html .= $this->buildGameHtml($previous_game,'previous');   
                }
            }
            
            $html .= '</div>';
            
            return $html;
	        
	    }
        
	}
	
	private function get_data() {
	    
	    if ($this->use_test) {
	        
	        $response = file_get_contents(__DIR__ .'/test/games.json', FILE_USE_INCLUDE_PATH);
	        if ($response == false) {
	            return null;
	        } else {
	            
	            $body = json_decode($response, true);
                $games = $body['sports'][0]['leagues'][0]['events'];
                
                return $games;
	        }
	        
	    } else {
	        
	        $response = wp_remote_get($this->api_url. $this->api_key, array('timeout' => 120));
	        if (is_wp_error($response)) {
	            return null;
	        } else {
	            
	            $body = wp_remote_retrieve_body($response);
                $body = json_decode($body, true);
                $games = $body['sports'][0]['leagues'][0]['events'];
                
                return $games;   
	        }
	        
	    }
	    
	}
	
	private function buildGameHtml($game, $type) {
	    
	    $game_logo = 'http://s.espncdn.com/stitcher/sports/basketball/mens-college-basketball/events/'.$game['id'].'.png?templateId=espn.com.share.1';
        $game_clock = $game['clock'];
        $game_period = $game['period'];
        $game_state = $game['competitions'][0]['status']['state'];
	    
	    $away_team = $this->getAwayTeam($game['competitions'][0]['competitors']);
	    $away_logo = 'http://a.espncdn.com/combiner/i?img=/i/teamlogos/ncaa/500/'.$away_team['team']['id'].'.png&w=250&h=250&transparent=true';
	    $away_color = '#'.$away_team['team']['color'];
	    $away_bg = 'background-image: linear-gradient( to left, '.$away_color.', #fff );';
	    $away_score = $away_team['score'];
	    $away_record = '';
        if ($away_team['team']['record']['summary']) {
            $away_record = $away_team['team']['record']['summary'];   
        }
        $away_score_class = '';
        if ($away_team['isWinner']) { $away_score_class = 'winner'; }
        else if (!$away_team['isWinner'] && $game_state == 'post') { $away_score_class = 'loser'; }
	    
        $home_team = $this->getHomeTeam($game['competitions'][0]['competitors']);
        $home_logo = 'http://a.espncdn.com/combiner/i?img=/i/teamlogos/ncaa/500/'.$home_team['team']['id'].'.png&w=250&h=250&transparent=true';
        $home_color = '#'.$home_team['team']['color'];
        $home_bg = 'background-image: linear-gradient( to left, '.$home_color.', #fff );';
        $home_score = $home_team['score'];
        $home_record = '';
        if ($home_team['team']['record']['summary']) {
            $home_record = $home_team['team']['record']['summary'];   
        }
        $home_score_class = '';
        if ($home_team['isWinner']) { $home_score_class = 'winner'; }
        else if (!$home_team['isWinner'] && $game_state == 'post') { $home_score_class = 'loser'; }
        
        $game_note = '';
        if ($game['notes'][0]) {
            $game_note = $game['notes'][0]['text'];
        }
        
        $game_status = '';
        if ($game_state == 'pre'){
            if ($game['timeValid']) {
                $game_date = date('l n/j g:i A', strtotime($game['date']));
                $game_status = $game_date;
            } else {
                $game_status = 'TBD';
            }
        } else {
            $game_status = $game['competitions'][0]['status']['shortDetail'];
        }
        
        $venue = '';
        if ($game['venues'][0]) {
            $venue = $game['venues'][0]['name'];
            if ($game['venues'][0]['city'] && $game['venues'][0]['state']) {
                $venue .= ', '.$game['venues'][0]['city'].', '.$game['venues'][0]['state'];
            }
        }
        
        $game_type_title = '';
        if ($type == 'previous') {
            $game_type_title = "Previous";
        } else if ($type == 'current') {
            if ($game_state == 'pre') {
                $game_type_title = "Upcoming";
            } else if ($game_state == 'in'){
                $game_type_title = "Live";
            } else {
                $game_type_title = "Current";
            }
        }
        
	    // THE GAME MARKUP
	    $html = '<div class="game-type">'.$game_type_title.'</div>';
	    $html .= '<div class="game '.$type.' '.$game_state.'">';
	        $html .= '<div class="game-row status">'.$game_status.'</div>';
            $html .= '<div class="game-row team away">';
                $html .= '<div class="logo"><img src="'.$away_logo.'"></div>';
                $html .= '<div class="meta">';
                    $html .= '<div class="name">'.$away_team['team']['location'].'</div>';
                    $html .= '<div class="record">'.$away_record.'</div>';
                $html .= '</div>';
                $html .= '<div class="score '.$away_score_class.'">'.($game_state != 'pre' ? $away_score : '').'</div>';
            $html .= '</div>';
            $html .= '<div class="game-row team home">';
                $html .= '<div class="logo"><img src="'.$home_logo.'"></div>';
                $html .= '<div class="meta">';
                    $html .= '<div class="name">'.$home_team['team']['location'].'</div>';
                    $html .= '<div class="record">'.$home_record.'</div>';
                $html .= '</div>';
                $html .= '<div class="score '.$home_score_class.'">'.($game_state != 'pre' ? $home_score : '').'</div>';
            $html .= '</div>';
            if ($venue != '') {
                $html .= '<div class="game-row venue">'.$venue.'</div>';   
            }
            if ($game_note != '') {
                $html .= '<div class="game-row note">'.$game_note.'</div>';   
            }
        $html .= '</div>';
	    
	    return $html;
	}
	
	private function getHomeTeam($competitors) {
        if ($competitors[0]['homeAway'] == 'home') {
            $home_team = $competitors[0];
        } else {
            $home_team = $competitors[1];
        }
        return $home_team;
	}
	
	private function getAwayTeam($competitors) {
        if ($competitors[0]['homeAway'] == 'away') {
            $away_team = $competitors[0];
        } else {
            $away_team = $competitors[1];
        }
        return $away_team;
	}
	
	public function register_plugin_styles() {
		wp_register_style('etsu-bball-games-styles', plugins_url( 'etsu-bball-games/assets/styles.min.css' ), array(), null);
		wp_enqueue_style('etsu-bball-games-styles');
	}

	public function get_etsu_bball_games($atts){
	    
	    if ( isset($atts['apikey']) ) {
            
            $etsu_bball_games = new ETSU_Bball_Games($atts);
		    
		    //return $etsu_bball_games->get_data();
		    return $etsu_bball_games->get_games();
		    
	    } else if( !isset($atts['apikey']) ) {
            
            $html = '<div class="alert alert-danger">';
            $html .= '<strong>API key not provided.</strong>';
            $html .= '</div>';
            return $html;
        }
	    
	}

}

$etsu_bball_games = new ETSU_Bball_Games;


