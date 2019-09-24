<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets manager base class
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>, Github: https://github.com/alexkovalevv
 * @copyright (c) 10.09.20198, Webcraftic
 * @since         2.0
 */
class WGZ_Check_Conditions {

	protected $condition;

	public function __construct( $condition ) {
		if ( empty( $condition ) ) {
			$this->condition = [];
		} else {
			$condition       = @json_decode( stripslashes( $condition ) );
			$this->condition = $condition;
		}
	}

	/**
	 * Проверяем в правильном ли формате нам передано условие
	 *
	 * @since  2.2.0
	 *
	 * @param \stdClass $condition
	 *
	 * @return bool
	 */
	protected function validate_condition_schema( $condition ) {
		$isset_attrs = ! empty( $condition->param ) && ! empty( $condition->operator ) && ! empty( $condition->type ) && isset( $condition->value );

		$allow_params = in_array( $condition->param, [
			'user-role',
			'user-mobile',
			'user-cookie-name',
			'current-url',
			'location-page',
			'regular-expression',
			'location-some-page',
			'location-post-type',
			'location-taxonomy'
		] );

		$allow_operators = in_array( $condition->operator, [
			'equals',
			'notequal',
			'less',
			'older',
			'greater',
			'younger',
			'contains',
			'notcontain',
			'between'
		] );

		$allow_types = in_array( $condition->type, [ 'select', 'text', 'default', 'regexp' ] );

		return $isset_attrs && $allow_params && $allow_operators && $allow_types;
	}

	/**
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 * @return bool
	 */
	public function validate() {
		if ( empty( $this->condition ) && ! is_array( $this->condition ) ) {
			return false;
		}

		$or = null;
		foreach ( $this->condition as $group_OR ) {
			if ( ! empty( $group_OR->conditions ) && is_array( $group_OR->conditions ) ) {
				$and = null;
				foreach ( $group_OR->conditions as $condition ) {
					if ( $this->validate_condition_schema( $condition ) ) {
						$method_name = str_replace( '-', '_', $condition->param );
						if ( is_null( $and ) ) {
							$and = $this->call_method( $method_name, $condition->operator, $condition->value );
						} else {
							$and = $and && $this->call_method( $method_name, $condition->operator, $condition->value );
						}
					}
				}

				$or = is_null( $or ) ? $and : $or || $and;
			}
		}

		return is_null( $or ) ? false : $or;
	}

	/**
	 * Call specified method
	 *
	 * @param $method_name
	 * @param $operator
	 * @param $value
	 *
	 * @return bool
	 */
	protected function call_method( $method_name, $operator, $value ) {
		if ( method_exists( $this, $method_name ) ) {
			return $this->$method_name( $operator, $value );
		} else {
			return apply_filters( 'wam/check_conditions', false, $method_name, $operator, $value );
		}
	}

	/**
	 * Determines whether the user's browser has a cookie with a given name
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	protected function user_cookie_name( $operator, $value ) {
		if ( isset( $_COOKIE[ $value ] ) ) {
			return $operator === 'equals';
		} else {
			return $operator === 'notequal';
		}
	}

	/**
	 * Get current URL
	 *
	 * @return string
	 */
	protected function get_current_url_path() {
		$url = explode( '?', $_SERVER['REQUEST_URI'], 2 );

		return ! empty( $url[0] ) ? trailingslashit( $url[0] ) : '/';
	}

	/**
	 * Get referer URL
	 *
	 * @return string
	 */
	protected function get_referer_url() {
		$out = "";
		$url = explode( '?', str_replace( site_url(), '', $_SERVER['HTTP_REFERER'] ), 2 );
		if ( isset( $url[0] ) ) {
			$out = trim( $url[0], '/' );
		}

		return $out ? urldecode( $out ) : '/';
	}

	/**
	 * Check by operator
	 *
	 * @param $operator
	 * @param $first
	 * @param $second
	 * @param $third
	 *
	 * @return bool
	 */
	public function apply_operator( $operator, $first, $second, $third = false ) {
		switch ( $operator ) {
			case 'equals':
				return $first === $second;
			case 'notequal':
				return $first !== $second;
			case 'less':
			case 'older':
				return $first > $second;
			case 'greater':
			case 'younger':
				return $first < $second;
			case 'contains':
				return strpos( $first, $second ) !== false;
			case 'notcontain':
				return strpos( $first, $second ) === false;
			case 'between':
				return $first < $second && $second < $third;

			default:
				return $first === $second;
		}
	}

	/**
	 * A role of the user who views your website. The role "guest" is applied for unregistered users.
	 *
	 * @param string $operator
	 * @param string $value
	 *
	 * @return boolean
	 */
	protected function user_role( $operator, $value ) {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}

		if ( ! is_user_logged_in() ) {
			return $this->apply_operator( $operator, $value, 'guest' );
		} else {
			$current_user = wp_get_current_user();
			if ( ! ( $current_user instanceof WP_User ) ) {
				return false;
			}

			return $this->apply_operator( $operator, $value, $current_user->roles[0] );
		}
	}

	/**
	 * Get timestamp
	 *
	 * @param $units
	 * @param $count
	 *
	 * @return integer
	 */
	protected function get_timestamp( $units, $count ) {
		switch ( $units ) {
			case 'seconds':
				return $count;
			case 'minutes':
				return $count * MINUTE_IN_SECONDS;
			case 'hours':
				return $count * HOUR_IN_SECONDS;
			case 'days':
				return $count * DAY_IN_SECONDS;
			case 'weeks':
				return $count * WEEK_IN_SECONDS;
			case 'months':
				return $count * MONTH_IN_SECONDS;
			case 'years':
				return $count * YEAR_IN_SECONDS;

			default:
				return $count;
		}
	}

	/**
	 * Get date timestamp
	 *
	 * @param $value
	 *
	 * @return integer
	 */
	public function get_date_timestamp( $value ) {
		if ( is_object( $value ) ) {
			return ( current_time( 'timestamp' ) - $this->get_timestamp( $value->units, $value->unitsCount ) ) * 1000;
		} else {
			return $value;
		}
	}

	/**
	 * The date when the user who views your website was registered.
	 * For unregistered users this date always equals to 1 Jan 1970.
	 *
	 * @param string $operator
	 * @param string $value
	 *
	 * @return boolean
	 */
	/*protected function user_registered( $operator, $value ) {
		if ( ! is_user_logged_in() ) {
			return false;
		} else {
			$user       = wp_get_current_user();
			$registered = strtotime( $user->data->user_registered ) * 1000;

			if ( $operator == 'equals' || $operator == 'notequal' ) {
				$registered = $registered / 1000;
				$timestamp  = round( $this->get_date_timestamp( $value ) / 1000 );

				return $this->apply_operator( $operator, date( "Y-m-d", $timestamp ), date( "Y-m-d", $registered ) );
			} else if ( $operator == 'between' ) {
				$start_timestamp = $this->get_date_timestamp( $value->start );
				$end_timestamp   = $this->get_date_timestamp( $value->end );

				return $this->apply_operator( $operator, $start_timestamp, $registered, $end_timestamp );
			} else {
				$timestamp = $this->get_date_timestamp( $value );

				return $this->apply_operator( $operator, $timestamp, $registered );
			}
		}
	}*/

	/**
	 * Check the user views your website from mobile device or not
	 *
	 * @param string $operator
	 * @param string $value
	 *
	 * @return boolean
	 *
	 * @link https://stackoverflow.com/a/4117597
	 */
	protected function user_mobile( $operator, $value ) {
		$useragent = $_SERVER['HTTP_USER_AGENT'];

		if ( preg_match( '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent ) || preg_match( '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr( $useragent, 0, 4 ) ) ) {
			return $operator === 'equals' && $value === 'yes' || $operator === 'notequal' && $value === 'no';
		} else {
			return $operator === 'notequal' && $value === 'yes' || $operator === 'equals' && $value === 'no';
		}
	}

	/**
	 * A some selected page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	protected function location_some_page( $operator, $value ) {
		$post_id = ( ! is_404() && ! is_search() && ! is_archive() && ! is_home() ) ? get_the_ID() : false;

		switch ( $value ) {
			case 'base_web':    // Basic - Entire Website
				$result = true;
				break;
			case 'base_sing':   // Basic - All Singulars
				$result = is_singular();
				break;
			case 'base_arch':   // Basic - All Archives
				$result = is_archive();
				break;
			case 'spec_404':    // Special Pages - 404 Page
				$result = is_404();
				break;
			case 'spec_search': // Special Pages - Search Page
				$result = is_search();
				break;
			case 'spec_blog':   // Special Pages - Blog / Posts Page
				$result = is_home();
				break;
			case 'spec_front':  // Special Pages - Front Page
				$result = is_front_page();
				break;
			case 'spec_date':   // Special Pages - Date Archive
				$result = is_date();
				break;
			case 'spec_auth':   // Special Pages - Author Archive
				$result = is_author();
				break;
			case 'post_all':    // Posts - All Posts
			case 'page_all':    // Pages - All Pages
				$result = false;
				if ( false !== $post_id ) {
					$post_type = 'post_all' == $value ? 'post' : 'page';
					$result    = $post_type == get_post_type( $post_id );
				}
				break;
			case 'post_arch':   // Posts - All Posts Archive
			case 'page_arch':   // Pages - All Pages Archive
				$result = false;
				if ( is_archive() ) {
					$post_type = 'post_arch' == $value ? 'post' : 'page';
					$result    = $post_type == get_post_type();
				}
				break;
			case 'post_cat':    // Posts - All Categories Archive
			case 'post_tag':    // Posts - All Tags Archive
				$result = false;
				if ( is_archive() && 'post' == get_post_type() ) {
					$taxonomy = 'post_tag' == $value ? 'post_tag' : 'category';
					$obj      = get_queried_object();

					$current_taxonomy = '';
					if ( '' !== $obj && null !== $obj ) {
						$current_taxonomy = $obj->taxonomy;
					}

					if ( $current_taxonomy == $taxonomy ) {
						$result = true;
					}
				}
				break;

			default:
				$result = true;
		}

		return $this->apply_operator( $operator, $result, true );
	}

	/**
	 * Проверяет текущий URL страницы.
	 *
	 * Если url в условии и url текущей страницы совпадают,
	 * условие будет выполнено успешно.
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 *
	 * @param string $operator
	 * @param string $value
	 */
	protected function current_url( $operator, $value ) {
		return $this->apply_operator( $operator, $value, $this->get_current_url_path() );
	}

	/**
	 * Проверяет пользовательское регулярное выражение
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 * @since  2.0.0
	 *
	 * @param string $operator
	 * @param string $value
	 */
	protected function regular_expression( $operator, $value ) {
		$current_url_path = $this->get_current_url_path();

		$check_url = ltrim( $current_url_path, '/\\' );
		$regexp    = trim( str_replace( '\\\\', '\\', $value ), '/' );

		return @preg_match( "/{$regexp}/", $check_url );
	}

	/**
	 * Проверяет проивольный url с маской
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	protected function location_page( $operator, $value ) {
		$first_url_path  = str_replace( site_url(), '', $value );
		$second_url_path = $this->get_current_url_path();

		if ( ! strpos( $first_url_path, '*' ) ) {
			return $this->apply_operator( $operator, $second_url_path, $first_url_path );
		}

		// Получаем строку до *
		$first_url_path = strstr( $first_url_path, '*', true );
		// Если это был не пустой url (типа http://site/*) и есть вхождение с начала
		if ( untrailingslashit( $first_url_path ) && strpos( untrailingslashit( $second_url_path ), $first_url_path ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * A referrer URL which has brought a user to the current page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	protected function location_referrer( $operator, $value ) {
		$url = $this->get_referer_url();

		return $url ? $this->apply_operator( $operator, trim( $url, '/' ), trim( $value, '/' ) ) : false;
	}

	/**
	 * A post type of the current page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	protected function location_post_type( $operator, $value ) {
		if ( is_singular() ) {
			return $this->apply_operator( $operator, $value, get_post_type() );
		}

		return false;
	}

	/**
	 * A taxonomy of the current page
	 *
	 * @since 2.2.8 The bug is fixed, the condition was not checked
	 *              for tachonomies, only posts.
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	protected function location_taxonomy( $operator, $value ) {
		$term_id = null;

		if ( is_tax() || is_tag() || is_category() ) {
			$term_id = get_queried_object()->term_id;

			if ( $term_id ) {
				return $this->apply_operator( $operator, intval( $value ), $term_id );
			}
		}

		return false;
	}


}