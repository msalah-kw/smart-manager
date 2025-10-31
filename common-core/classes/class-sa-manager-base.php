<?php
/**
 * Common core base class.
 *
 * @package common-core/
 * @since       8.64.0
 * @version     8.67.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SA_Manager_Base' ) ) {
	/**
	 * Class properties and methods will go here.
	 */
	class SA_Manager_Base {
		/**
		 * Current dashboard key
		 *
		 * @var string
		 */
		public $dashboard_key = '';

		/**
		 * Stores the plugin SKU
		 *
		 * @var string
		 */
		public $plugin_sku = '';

		/**
		 * Current post type
		 *
		 * @var string
		 */
		public $post_type = '';

		/**
		 * An array containing required parameters for the operation.
		 *
		 * @var array
		 */
		public $req_params = array();

		/**
		 *  Plugin path.
		 *
		 * @var string
		 */
		public $plugin_path = '';

		/**
		 * Current dashboard title
		 *
		 * @var string
		 */
		public $dashboard_title = '';

		/**
		 * Name of the transient option used for store column model data.
		 *
		 * @var string
		 */
		public $store_col_model_transient_option_nm = '';

		/**
		 * Holds the default store.
		 *
		 * @var array $default_store_model
		 */
		public $default_store_model = array();

		/**
		 * An array to store term values associated with their parent terms.
		 *
		 * @var array $terms_val_parent
		 */
		public $terms_val_parent = array();

		/**
		 * Constructor is called when the class is instantiated
		 *
		 * @param array $plugin_data $plugin_data Current plugin data array.
		 * @return void
		 */
		public function __construct( $plugin_data = array() ) {
			$this->dashboard_key = ( ! empty( $plugin_data['dashboard_key'] ) ) ? $plugin_data['dashboard_key'] : '';
			$this->plugin_sku    = ( ! empty( $plugin_data['plugin_sku'] ) ) ? $plugin_data['plugin_sku'] : '';
			$this->post_type     = ( ! empty( $this->dashboard_key ) ) ? $this->dashboard_key : '';
			$this->plugin_path   = untrailingslashit( plugin_dir_path( __FILE__ ) );
			// Sanitize $_REQUEST recursively using sanitize_text_field, similar to wc_clean.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->req_params = ( ! empty( $_REQUEST ) )
				? ( function_exists( 'wc_clean' )
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					? wc_clean( wp_unslash( $_REQUEST ) )
					: array_map(
						function( $value ) {
							if ( is_array( $value ) ) {
								return array_map( 'sanitize_text_field', $value );
							}
							return is_scalar( $value ) ? sanitize_text_field( $value ) : $value;
						},
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						wp_unslash( $_REQUEST )
					)
				)
				: array();
			$this->dashboard_title                     = ( ! empty( $this->req_params['active_module_title'] ) ) ? $this->req_params['active_module_title'] : '';
			$this->store_col_model_transient_option_nm = 'sa_' . $this->plugin_sku . '_' . $this->dashboard_key;
		}

		/**
		 * Get type from data type
		 *
		 * @param string $data_type data type.
		 * @return string $type Updated type
		 */
		public function get_type_from_data_type( $data_type = '' ) {
			$type = 'text';
			if ( empty( $data_type ) ) {
				return $type;
			}
			$type_strpos = strrpos( $data_type, '(' );
			if ( false !== $type_strpos ) {
				$type = substr( $data_type, 0, $type_strpos );
			} else {
				$types = explode( ' ', $data_type ); // for handling types with attributes (biginit unsigned).
				$type  = $types[0];
			}
			switch ( $type ) {
				case ( 'int' === substr( $type, -3 ) ):
					$type = 'numeric';
					break;
				case ( 'char' === substr( $type, -4 ) || 'text' === substr( $type, -4 ) ):
					$type = ( 'longtext' === $type ) ? $this->plugin_sku . '.longstring' : 'text';
					break;
				case ( 'blob' === substr( $type, -4 ) ):
					$type = $this->plugin_sku . '.longstring';
					break;
				case ( 'datetime' === $type || 'timestamp' === $type ):
					$type = $this->plugin_sku . '.datetime';
					break;
				case ( 'date' === $type || 'year' === $type ):
					$type = $this->plugin_sku . '.date';
					break;
				case ( 'decimal' === $type || 'float' === $type || 'double' === $type || 'real' === $type ):
					$type = 'numeric';
					break;
				case ( 'boolean' === $type ):
					$type = 'checkbox';
					break;
				default:
					$type = 'text';
			}
			return $type;
		}

		/**
		 * Get type from value
		 *
		 * @param string $value value.
		 * @return string $type Updated type
		 */
		public function get_type_from_value( $value = '' ) {
			$type = 'text';
			if ( empty( $value ) ) {
				return $type;
			}
			$checkbox_values = array( 'yes', 'no', 'true', 'false' );
			switch ( $value ) {
				case ( ! empty( in_array( $value, $checkbox_values, true ) ) || ( is_numeric( $value ) && ( '0' === $value || '1' === $value ) ) ):
					$type = 'checkbox';
					break;
				case ( is_numeric( $value ) ):
					if ( function_exists( 'isTimestamp' ) ) {
						if ( isTimestamp( $value ) ) {
							$type = $this->plugin_sku . '.datetime';
							break;
						}
					}
					if ( $this->plugin_sku . '.datetime' !== $type ) {
						$type = 'numeric';
					}
					break;
				case ( is_serialized( $value ) === true ):
					$type = $this->plugin_sku . '.serialized';
					break;
				case ( DateTime::createFromFormat( 'Y-m-d H:i:s', $value ) !== false ):
					$type = $this->plugin_sku . '.datetime';
					break;
				case ( DateTime::createFromFormat( 'Y-m-d', $value ) !== false ):
					$type = $this->plugin_sku . '.date';
					break;
				default:
					$type = 'text';
			}
			return $type;
		}

		/**
		 * Get column type
		 *
		 * @param string $data_type data type.
		 * @param string $value value.
		 * @return string Updated column type
		 */
		public function get_col_type( $data_type = '', $value = '' ) {
			return ( ! empty( $data_type ) ) ? $this->get_type_from_data_type( $data_type ) : $this->get_type_from_value( $value );
		}

		/**
		 * Get default column model
		 *
		 * @param array $args args array.
		 * @return array $column Updated column model
		 */
		public function get_default_column_model( $args = array() ) {
			global $wpdb;
			$column = array();
			if ( empty( $args ) ) {
				return $column;
			}
			if ( empty( $args['table_nm'] ) || empty( $args['col'] ) ) {
				return $column;
			}
			$col = $args['col'];
			unset( $args['col'] );
			$table_nm = $args['table_nm'];
			unset( $args['table_nm'] );
			$visible_cols = array();
			if ( ! empty( $args['visible_cols'] ) ) {
				$visible_cols = $args['visible_cols'];
				unset( $args['visible_cols'] );
			}
			$is_meta = false;
			if ( ! empty( $args['is_meta'] ) ) {
				$is_meta = true;
				unset( $args['is_meta'] );
			}
			$src  = $table_nm . '/' . ( ( ! empty( $is_meta ) ) ? 'meta_key=' . $col . '/meta_value=' . $col : $col ); // phpcs:ignore
			$name = ( ! empty( $args['name'] ) ) ? $args['name'] : ucwords( str_replace( '_', ' ', $col ) ); // phpcs:ignore
			if ( isset( $args['name'] ) ) {
				unset( $args['name'] );
			}
			// Code to get the col type.
			$data_type = '';
			if ( ! empty( $args['db_type'] ) ) {
				$data_type = $args['db_type'];
				unset( $args['db_type'] );
			}
			$col_value = '';
			if ( ! empty( $args['col_value'] ) ) {
				$col_value = $args['col_value'];
				unset( $args['col_value'] );
			}
			$uneditable_types = array( $this->plugin_sku . '.longstring' );
			$type             = $this->get_col_type( $data_type, $col_value );
			if ( ! empty( $args['values'] ) && empty( $args['search_values'] ) ) {
				$args['search_values'] = array();
				foreach ( $args['values'] as $key => $value ) {
					$args['search_values'][] = array(
						'key'   => $key,
						'value' => $value,
					);
				}
			}
			$default_widths = apply_filters(
				$this->plugin_sku . '_default_col_widths',
				array(
					$this->plugin_sku . '.image'      => 50,
					'numeric'                         => 50,
					'checkbox'                        => 30,
					$this->plugin_sku . '.datetime'   => 105,
					'text'                            => 130,
					$this->plugin_sku . '.longstring' => 150,
					$this->plugin_sku . '.serialized' => 200,
				)
			);
			$column         = array_merge(
				array(
					'src'            => $src,
					'data'           => sanitize_title( str_replace( array( '/', '=' ), '_', $src ) ), // generate slug using the wordpress function if not given.
					'name'           => $name,
					'key'            => $name,
					'type'           => $type,
					'editor'         => ( 'numeric' === $type ) ? 'customNumericEditor' : $type,
					'hidden'         => false,
					'editable'       => ( empty( in_array( $type, $uneditable_types, true ) ) ) ? true : false,
					'batch_editable' => true,
					'allow_showhide' => true,
					'sortable'       => true,
					'resizable'      => true,
					'exportable'     => true,
					'searchable'     => true,
					'frozen'         => false,  // For disabling frozen.
					'wordWrap'       => false,  // For disabling word-wrap.
					'save_state'     => true,
					'editor_schema'  => false,
					'align'          => ( 'numeric' === $type ) ? 'right' : 'left',
					'table_name'     => $wpdb->prefix . $table_nm,
					'col_name'       => ( ! empty( $args['col_name'] ) ) ? $args['col_name'] : $col,
					'width'          => ( ! empty( $default_widths[ $type ] ) ) ? $default_widths[ $type ] : 200,
					'values'         => array(),
					'search_values'  => array(),
					'category'       => '',
					'placeholder'    => '',
				),
				$args
			);
			if ( strpos( $col, '_phone' ) !== false || strpos( $col, '_tel' ) !== false || strpos( $col, 'phone_' ) !== false || strpos( $col, 'tel_' ) !== false ) {
				$column['validator'] = 'customPhoneTextEditor';
			}
			if ( ( ! empty( $is_meta ) && ( '_thumbnail_id' === $col || 'thumbnail_id' === $col ) ) || $this->plugin_sku . '.image' === $type ) {
				$column['name']     = _x( 'Featured Image', 'column name', 'smart-manager-for-wp-e-commerce' );
				$args['key']        = _x( 'Featured Image', 'column name key', 'smart-manager-for-wp-e-commerce' );
				$column['type']     = $this->plugin_sku . '.image';
				$column['editable'] = false;
			}
			if ( 'checkbox' === $type ) {
				if ( 'yes' === $col_value || 'no' === $col_value ) {
					$column['checkedTemplate']   = 'yes';
					$column['uncheckedTemplate'] = 'no';
				} elseif ( '0' === $col_value || '1' === $col_value ) {
					$column['checkedTemplate']   = 1;
					$column['uncheckedTemplate'] = 0;
				}
			}
			if ( function_exists( 'isTimestamp' ) ) {
				if ( isTimestamp( $col_value ) && $this->plugin_sku . '.datetime' === $type ) {
					$column['date_type'] = 'timestamp';
				}
			}
			if ( ! empty( $visible_cols ) && is_array( $visible_cols ) ) {
				if ( ! empty( $column['position'] ) ) {
					unset( $column['position'] );
				}
				$position = array_search( $column['data'], $visible_cols, true );
				if ( false !== $position ) {
					$column['position'] = $position + 1;
					$column['hidden']   = false;
				} else {
					$column['hidden'] = true;
				}
			}
			return $column;
		}

		/**
		 * Get default store model
		 *
		 * @return void
		 */
		public function get_default_store_model() {
			global $wpdb;
			$col_model             = array();
			$ignored_col           = array( 'post_type' );
			$default_col_positions = array( 'ID', 'post_title', 'post_content', 'post_status', 'post_date', 'post_name' );
			$visible_cols          = array( 'ID', 'post_title', 'post_date', 'post_name', 'post_status', 'post_content' );
			$hidden_cols           = array( '_edit_lock', '_edit_last' );
			$col_titles            = array(
				'post_date'     => $this->dashboard_title . ' Created Date',
				'post_date_gmt' => $this->dashboard_title . ' Created Date Gmt',
			);
			$results_posts_col     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"SHOW COLUMNS FROM {$wpdb->prefix}posts",
				'ARRAY_A'
			);
			$posts_num_rows        = $wpdb->num_rows;
			$last_position         = 0;
			$field_nm              = '';
			if ( $posts_num_rows > 0 && ( ! empty( $results_posts_col ) ) && is_array( $results_posts_col ) ) {
				foreach ( $results_posts_col as $posts_col ) {
					$field_nm = ( ! empty( $posts_col['Field'] ) ) ? $posts_col['Field'] : '';
					if ( in_array( $field_nm, $ignored_col, true ) ) {
						continue;
					}
					$args = array(
						'table_nm' => 'posts',
						'col'      => $field_nm,
						'name'     => ( ! empty( in_array( $field_nm, $col_titles, true ) ) ) ? $col_titles[ $field_nm ] : '',
						'hidden'   => ( empty( in_array( $field_nm, $visible_cols, true ) ) ) ? true : false,
						'db_type'  => ( ! empty( $posts_col['Type'] ) ) ? $posts_col['Type'] : '',
					);
					// Code for handling extra meta for the columns.
					if ( 'ID' === $field_nm ) {
						$args['editor']         = false;
						$args['batch_editable'] = false;
					} elseif ( 'post_status' === $field_nm ) {
						$args['type']         = 'dropdown';
						$args['strict']       = true;
						$args['allowInvalid'] = false;
						if ( 'page' === $this->dashboard_key ) {
							$args['values'] = get_page_statuses();
						} else {
							$statuses       = get_post_stati( array(), 'object' );
							$args['values'] = array();
							// Code for creating unused_statuses array.
							$unused_post_statuses = array( 'inherit', 'trash', 'auto-draft', 'in-progress', 'failed', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' );
							if ( function_exists( 'wc_get_order_statuses' ) ) {
								$unused_post_statuses = array_merge( $unused_post_statuses, array_keys( wc_get_order_statuses() ) );
							}
							if ( function_exists( 'wcs_get_subscription_statuses' ) ) {
								$unused_post_statuses = array_merge( $unused_post_statuses, array_keys( wcs_get_subscription_statuses() ) );
							}
							$unused_post_statuses = apply_filters( $this->plugin_sku . '_unused_post_statuses', $unused_post_statuses );
							foreach ( $statuses as $status ) {
								if ( in_array( $status->name, $unused_post_statuses, true ) ) {
									continue;
								}
								$args['values'][ $status->name ] = $status->label;
							}
						}
						$args['defaultValue']  = 'draft';
						$args['editor']        = 'select';
						$args['selectOptions'] = $args['values'];
						$args['renderer']      = 'selectValueRenderer';
					} elseif ( 'post_excerpt' === $field_nm ) {
						$args['type'] = $this->plugin_sku . '.longstring';
					}
					// Code for setting the default column positions.
					$position = array_search( $field_nm, $default_col_positions, true );
					if ( false !== $position ) {
						$args['position'] = $position + 1;
						$last_position++;
					}
					$col_model[] = $this->get_default_column_model( $args );
				}
			}
			// Code to get columns from postmeta table.
			$results_postmeta_col = array();
			if ( is_array( $this->post_type ) ) {
				$results_postmeta_col = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT DISTINCT {$wpdb->prefix}postmeta.meta_key,
												{$wpdb->prefix}postmeta.meta_value
											FROM {$wpdb->prefix}postmeta 
												JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.id = {$wpdb->prefix}postmeta.post_id)
											WHERE {$wpdb->prefix}postmeta.meta_key != '' 
												AND {$wpdb->prefix}postmeta.meta_key NOT LIKE %s
												AND {$wpdb->prefix}postmeta.meta_key NOT LIKE %s
												AND {$wpdb->prefix}postmeta.meta_key NOT LIKE %s
												AND {$wpdb->prefix}posts.post_type IN (" . implode( ',', array_fill( 0, count( $this->post_type ), '%s' ) ) . ")
											GROUP BY {$wpdb->prefix}postmeta.meta_key",
						array_merge(
							array( 'free-%', '_oembed%', 'xts-blocks%' ),
							$this->post_type
						)
					),
					'ARRAY_A'
				);
			} else {
				$results_postmeta_col = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT DISTINCT {$wpdb->prefix}postmeta.meta_key,
												{$wpdb->prefix}postmeta.meta_value
											FROM {$wpdb->prefix}postmeta 
												JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.id = {$wpdb->prefix}postmeta.post_id)
											WHERE {$wpdb->prefix}postmeta.meta_key != '' 
												AND {$wpdb->prefix}postmeta.meta_key NOT LIKE %s
												AND {$wpdb->prefix}postmeta.meta_key NOT LIKE %s
												AND {$wpdb->prefix}postmeta.meta_key NOT LIKE %s
												AND {$wpdb->prefix}posts.post_type = %s
											GROUP BY {$wpdb->prefix}postmeta.meta_key",
						'free-%',
						'_oembed%',
						'xts-blocks%',
						$this->post_type
					),
					'ARRAY_A'
				);
			}
			$num_rows = $wpdb->num_rows;
			if ( $num_rows > 0 ) {
				$meta_keys = array();
				if ( ! empty( $results_postmeta_col ) && is_array( $results_postmeta_col ) ) {
					foreach ( $results_postmeta_col as $key => $postmeta_col ) {
						if ( empty( $postmeta_col['meta_value'] ) || '1' === $postmeta_col['meta_value'] || '0.00' === $postmeta_col['meta_value'] ) {
							$meta_keys [] = $postmeta_col['meta_key']; // TODO: if possible store in db instead of using an array.
						}
						unset( $results_postmeta_col[ $key ] );
						$results_postmeta_col[ $postmeta_col['meta_key'] ] = $postmeta_col; // phpcs:ignore
					}
				}
				// not in 0 added for handling empty date columns.
				if ( ! empty( $meta_keys ) && is_array( $meta_keys ) ) {
					$results_meta_value  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT {$wpdb->prefix}postmeta.meta_key,
							{$wpdb->prefix}postmeta.meta_value
							FROM {$wpdb->prefix}postmeta 
								JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.id = {$wpdb->prefix}postmeta.post_id)
							WHERE {$wpdb->prefix}posts.post_type = %s
								AND {$wpdb->prefix}postmeta.meta_value NOT IN ('','0','0.00','1')
								AND {$wpdb->prefix}postmeta.meta_key IN (" . implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ) . ")
							GROUP BY {$wpdb->prefix}postmeta.meta_key",
							array_merge(
								array( $this->dashboard_key ),
								$meta_keys
							)
						),
						'ARRAY_A'
					);
					$num_rows_meta_value = $wpdb->num_rows;
					if ( $num_rows_meta_value > 0 && ( ! empty( $results_meta_value ) ) && is_array( $results_meta_value ) ) {
						foreach ( $results_meta_value as $result_meta_value ) {
							if ( isset( $results_postmeta_col [ $result_meta_value['meta_key'] ] ) ) {
								$results_postmeta_col [ $result_meta_value['meta_key'] ]['meta_value'] = $result_meta_value['meta_value']; // phpcs:ignore
							}
						}
					}
				}
				// Filter to add custom postmeta columns for custom plugins.
				$results_postmeta_col = apply_filters( $this->plugin_sku . '_default_dashboard_model_postmeta_cols', $results_postmeta_col );
				$meta_count           = 0;
				// Code for pkey column for postmeta.
				$col_model[] = $this->get_default_column_model(
					array(
						'table_nm'       => 'postmeta',
						'col'            => 'post_id',
						'type'           => 'numeric',
						'hidden'         => true,
						'allow_showhide' => false,
						'editor'         => false,
					)
				);
				if ( ( ! empty( $results_postmeta_col ) ) && is_array( $results_postmeta_col ) ) {
					foreach ( $results_postmeta_col as $postmeta_col ) {
						$meta_key   = ( ! empty( $postmeta_col['meta_key'] ) ) ? $postmeta_col['meta_key'] : '';
						$meta_value = ( ! empty( $postmeta_col['meta_value'] ) || 0 === $postmeta_col['meta_value'] ) ? $postmeta_col['meta_value'] : '';
						$args       = array(
							'table_nm'  => 'postmeta',
							'col'       => $meta_key,
							'is_meta'   => true,
							'col_value' => $meta_value,
							'name'      => ( ! empty( in_array( $meta_key, $col_titles, true ) ) ) ? $col_titles[ $meta_key ] : '',
							'hidden'    => ( ! empty( in_array( $meta_key, $hidden_cols, true ) ) || $meta_count > 5 ) ? true : false,
						);
						if ( empty( $args['hidden'] ) ) {
							$last_position++;
						}
						$col_model[] = $this->get_default_column_model( $args );
						$meta_count++;
					}
				}
			}
			// Code to get columns from terms and get all relevant taxonomy for the post type.
			$taxonomy_nm = get_object_taxonomies( $this->post_type );
			if ( ! empty( $taxonomy_nm ) ) {
				$terms_count = 0;
				// Code for pkey column for terms.
				$col_model[]    = $this->get_default_column_model(
					array(
						'table_nm'       => 'terms',
						'col'            => 'object_id',
						'type'           => 'numeric',
						'hidden'         => true,
						'allow_showhide' => false,
						'editor'         => false,
					)
				);
				$taxonomy_terms = get_terms(
					$taxonomy_nm,
					array(
						'hide_empty' => 0,
						'orderby'    => 'name',
					)
				);
				if ( ! empty( $taxonomy_terms ) ) {
					$results = $this->get_parent_term_values(
						array(
							'taxonomy_obj'     => $taxonomy_terms,
							'include_taxonomy' => 'all', // include all taxonomy.
						)
					);
				}
				// Code for defining the col model for the terms.
				if ( is_array( $taxonomy_nm ) ) {
					foreach ( $taxonomy_nm as $taxonomy ) {
						$args = array(
							'table_nm' => 'terms',
							'col'      => $taxonomy,
							'name'     => ( ! empty( in_array( $field_nm, $col_titles, true ) ) ) ? $col_titles[ $field_nm ] : '',
							'hidden'   => ( $terms_count > 5 ) ? true : false,
						);
						if ( ! isset( $results['terms_val'] ) || ! isset( $results['terms_val_search'] ) ) {
							continue;
						}
						if ( ! empty( $results['terms_val'][ $taxonomy ] ) ) {
							$args['type']         = $this->plugin_sku . '.multilist';
							$args['strict']       = true;
							$args['allowInvalid'] = false;
							$args['editable']     = false;
							$args['values']       = $results['terms_val'][ $taxonomy ];
							if ( ! empty( $results['terms_val_search'][ $taxonomy ] ) ) {
								$args['search_values'] = array();
								foreach ( $results['terms_val_search'][ $taxonomy ] as $key => $value ) {
									$args['search_values'][] = array(
										'key'   => $key,
										'value' => $value,
									);
								}
							}
						}
						if ( empty( $args['hidden'] ) ) {
							$last_position++;
						}
						$col_model[] = $this->get_default_column_model( $args );
						$terms_count++;
					}
				}
			}
			$col_model[] = $this->get_default_column_model(
				array(
					'table_nm'       => 'custom',
					'col'            => 'edit_link',
					'renderer'       => 'html',
					'name'           => _x( 'Edit', 'edit link', 'smart-manager-for-wp-e-commerce' ),
					'sortable'       => false,
					'editor'         => false,
					'searchable'     => false,
					'editable'       => false,
					'batch_editable' => false,
					'position'       => ++$last_position,
					'width'          => 30,
				)
			);
			$col_model[] = $this->get_default_column_model(
				array(
					'table_nm'       => 'custom',
					'col'            => 'view_link',
					'renderer'       => 'html',
					'name'           => _x( 'View', 'view link', 'smart-manager-for-wp-e-commerce' ),
					'sortable'       => false,
					'editor'         => false,
					'searchable'     => false,
					'editable'       => false,
					'batch_editable' => false,
					'position'       => ++$last_position,
					'width'          => 30,
				)
			);
			// defining the default col model.
			$this->default_store_model = array(
				'display_name'   => _x( 'Product', 'dashboard display name', 'smart-manager-for-wp-e-commerce' ),
				'tables'         => array(
					'posts'              => array(
						'pkey'    => 'ID',
						'join_on' => '',
						'where'   => array(
							'post_type'   => $this->post_type,
							'post_status' => 'any', // will get all post_status except 'trash' and 'auto-draft'.
						),
					),
					'postmeta'           => array(
						'pkey'    => 'post_id',
						'join_on' => 'postmeta.post_ID = posts.ID', // format current_table.pkey = joinning table.pkey.
						'where'   => array( // provide a wp_query [meta_query].
						),
					),
					'term_relationships' => array(
						'pkey'    => 'object_id',
						'join_on' => 'term_relationships.object_id = posts.ID',
						'where'   => array(),
					),
					'term_taxonomy'      => array(
						'pkey'    => 'term_taxonomy_id',
						'join_on' => 'term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id',
						'where'   => array(),
					),
					'terms'              => array(
						'pkey'    => 'term_id',
						'join_on' => 'terms.term_id = term_taxonomy.term_id',
						'where'   => array(),
					),
				),
				'columns'        => $col_model,
				'sort_params'    => array( // WP_Query array structure.
					'orderby' => 'ID', // multiple list separated by space.
					'order'   => 'DESC',
					'default' => true,
				),
				'per_page_limit' => '', // blank, 0, -1 all values refer to infinite scroll.
				'treegrid'       => false, // flag for setting the treegrid.
			);
		}

		/**
		 * Get the dashboard model
		 *
		 * @param boolean $return_store_model Whether need to return store model or not.
		 * @return array $store_model Updated store model
		 */
		public function get_dashboard_model( $return_store_model = false ) {
			if ( ( ! empty( $this->req_params['lang'] ) ) && ( class_exists( 'SitePress' ) ) && ( defined('SMPRO') && true === SMPRO ) ) {
				delete_transient( 'sa_sm_' . $this->dashboard_key );
			}
			global $wpdb, $current_user;
			$col_model                   = array();
			$search_params               = array();
			$old_col_model               = array();
			$column_model_transient      = ( ! empty( $this->store_col_model_transient_option_nm ) ) ? get_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, true ) : array();
			$column_model_transient_data = apply_filters(
				$this->plugin_sku . '_get_col_model_transient_data',
				array(
					'search_params'          => $search_params,
					'column_model_transient' => $column_model_transient,
				)
			);
			if ( ( ! empty( $column_model_transient_data ) ) && is_array( $column_model_transient_data ) ) {
				$column_model_transient = ( ! empty( $column_model_transient_data['column_model_transient'] ) ) ? $column_model_transient_data['column_model_transient'] : $column_model_transient;
				$search_params          = ( ! empty( $column_model_transient_data['search_params'] ) ) ? $column_model_transient_data['search_params'] : $search_params;
			}
			// Load from cache.
			$store_model_transient = ( ! empty( $this->store_col_model_transient_option_nm ) ) ? get_transient( $this->store_col_model_transient_option_nm ) : '';
			$store_model_transient = ( ! empty( $store_model_transient ) && ! is_array( $store_model_transient ) ) ? json_decode( $store_model_transient, true ) : $store_model_transient;
			// Code to move the column transients at user meta level.
			if ( empty( $column_model_transient ) ) {
				$key                    = $this->plugin_sku . '_' . $current_user->user_email . '_' . $this->dashboard_key;
				$column_model_transient = get_option( $key );
				if ( ! empty( $column_model_transient ) && ( ! empty( $this->store_col_model_transient_option_nm ) ) ) {
					update_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, $column_model_transient );
					delete_option( $key );
				}
			}
			if ( empty( $column_model_transient ) ) { // for getting the old structure.
				$column_model_transient = get_transient( 'sa_' . $this->plugin_sku . '_' . $current_user->user_email . '_' . $this->dashboard_key );
				if ( ! empty( $column_model_transient ) ) {
					delete_transient( 'sa_' . $this->plugin_sku . '_' . $current_user->user_email . '_' . $this->dashboard_key );
				}
			}
			$column_and_store_model_transient_data = apply_filters(
				$this->plugin_sku . '_get_col_and_store_model_transient_data',
				array(
					'column_model_transient' => $column_model_transient,
					'store_model_transient'  => $store_model_transient,
				)
			);

			$column_model_transient = ( ! empty( $column_and_store_model_transient_data ) ) ? $column_and_store_model_transient_data['column_model_transient'] : $column_model_transient;

			$store_model_transient = ( ! empty( $column_and_store_model_transient_data ) ) ? $column_and_store_model_transient_data['store_model_transient'] : $store_model_transient;

			$port_store_model_old_structure_data = apply_filters(
				$this->plugin_sku . '_port_store_model_old_structure',
				array(
					'store_model_transient' => $store_model_transient,
					'old_col_model'         => $old_col_model,
				)
			);

			$store_model_transient = ( ! empty( $port_store_model_old_structure_data ) ) ? $port_store_model_old_structure_data['store_model_transient'] : $store_model_transient;

			$old_col_model = ( ! empty( $port_store_model_old_structure_data ) ) ? $port_store_model_old_structure_data['old_col_model'] : $old_col_model;

			$store_model = $store_model_transient;
			// Valid cache not found.
			if ( empty( $store_model ) ) {
				$load_default_store_model = apply_filters( $this->plugin_sku . '_load_default_store_model', true );
				if ( ! empty( $load_default_store_model ) ) {
					$this->get_default_store_model();
				}
				// Filter to modify the default dashboard model.
				$this->default_store_model = apply_filters( $this->plugin_sku . '_default_dashboard_model', $this->default_store_model );
				$store_model               = ( ! empty( $this->default_store_model ) ) ? $this->default_store_model : array();
				$store_model               = apply_filters( $this->plugin_sku . '_get_store_model_data', $store_model );
			}
			// Filter to modify the dashboard model.
			$can_apply_dashboard_model_filter = apply_filters( 'sa_' . $this->plugin_sku . '_can_apply_dashboard_model_filter', true );
			$store_model                      = ( ! empty( $can_apply_dashboard_model_filter ) ) ? apply_filters( 'sa_' . $this->plugin_sku . '_dashboard_model', $store_model, $store_model_transient ) : $store_model;

			$store_model = apply_filters( $this->plugin_sku . '_port_store_model_new_mapping', $store_model, $old_col_model );

			$column_and_store_model_data_post_mapping = apply_filters(
				$this->plugin_sku . '_map_column_for_stored_transient',
				array(
					'column_model_transient' => $column_model_transient,
					'store_model'            => $store_model,
				)
			);

			$store_model            = ( ! empty( $column_and_store_model_data_post_mapping ) ) ? $column_and_store_model_data_post_mapping['store_model'] : $store_model;
			$column_model_transient = ( ! empty( $column_and_store_model_data_post_mapping ) ) ? $column_and_store_model_data_post_mapping['column_model_transient'] : $column_model_transient;
			// Code for re-arranging the columns in the final column model based on the set position.
			$final_column_model = ( ! empty( $store_model['columns'] ) ) ? $final_column_model = &$store_model['columns'] : '';
			if ( ! empty( $final_column_model ) ) {
				$priority_columns = array();
				foreach ( $final_column_model as $key => &$column_model ) {
					// checking for multilist datatype.
					if ( ! empty( $column_model['type'] ) && $this->plugin_sku . '.multilist' === $column_model['type'] ) {
						$col_exploded = ( ! empty( $column_model['src'] ) ) ? explode( '/', $column_model['src'] ) : array();
						if ( count( $col_exploded ) > 2 ) {
							$col_meta = explode( '=', $col_exploded[1] );
							$col_nm   = $col_meta[1];
						} else {
							$col_nm = $col_exploded[1];
						}
						$column_model['values'] = ( ! empty( $this->terms_val_parent[ $col_nm ] ) ) ? $this->terms_val_parent[ $col_nm ] : $column_model['values'];
					}
					if ( ! isset( $column_model['position'] ) ) {
						continue;
					}
					$priority_columns[] = $column_model;
					unset( $final_column_model[ $key ] );
				}
				if ( ! empty( $priority_columns ) || ! empty( $final_column_model ) ) {
					usort( $priority_columns, 'sa_position_compare' ); // code for sorting as per the position.
					$final_column_model = array_values( $final_column_model );
					foreach ( $final_column_model as $col_model ) {
						$priority_columns[] = $col_model;
					}
					ksort( $priority_columns );
					$store_model['columns'] = $priority_columns;
				}
			}
			// Valid cache not found.
			if ( ( ! empty( $this->store_col_model_transient_option_nm ) ) && false === get_transient( $this->store_col_model_transient_option_nm ) ) {
				set_transient( $this->store_col_model_transient_option_nm, wp_json_encode( $store_model ), WEEK_IN_SECONDS );
			}
			if ( ( ! empty( $this->store_col_model_transient_option_nm ) ) && empty( get_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, true ) ) ) {
				update_user_meta( get_current_user_id(), $this->store_col_model_transient_option_nm, $column_model_transient );
			}
			$store_model = apply_filters( $this->plugin_sku . '_modify_store_model_for_trash_status', $store_model );
			do_action( $this->plugin_sku . '_dashboard_model_saved' );
			$store_model = apply_filters( $this->plugin_sku . '_modify_store_model_search_params', $store_model, $search_params );
			if ( ! $return_store_model ) {
				wp_send_json( $store_model );
			} else {
				return $store_model;
			}
		}

		/**
		 * Function to get parent term values for taxonomies.
		 *
		 * @param array $args array of taxonomy related data.
		 * @return array terms data.
		 */
		public function get_parent_term_values( $args = array() ) {
			if ( empty( $args['include_taxonomy'] ) || ! is_array( $args['taxonomy_obj'] ) ) {
				return;
			}
			$terms_val        = array();
			$terms_val_search = array();
			// Code for storing the parent taxonomies titles.
			$taxonomy_parents = array();
			foreach ( $args['taxonomy_obj'] as $term_obj ) {
				if ( 'product_cat' === $args['include_taxonomy'] && ( 'product_cat' !== $term_obj->taxonomy ) ) {
					continue;
				}
				if ( empty( $term_obj->parent ) ) {
					$taxonomy_parents[ $term_obj->term_id ] = $term_obj->name;
				}
			}
			foreach ( $args['taxonomy_obj'] as $term_obj ) {
				if ( empty( $terms_val[ $term_obj->taxonomy ] ) ) {
					$terms_val[ $term_obj->taxonomy ] = array();
				}
				$title = ucwords( ( ! empty( $taxonomy_parents[ $term_obj->parent ] ) ) ? ( $taxonomy_parents[ $term_obj->parent ] . ' — ' . $term_obj->name ) : $term_obj->name );
				$terms_val[ $term_obj->taxonomy ][ $term_obj->term_id ]              = $title;
				$this->terms_val_parent[ $term_obj->taxonomy ][ $term_obj->term_id ] = array(
					'term'   => $term_obj->name,
					'parent' => $term_obj->parent,
					'title'  => $title,
				);
			}
			return array(
				'terms_val'        => $terms_val,
				'terms_val_search' => $terms_val_search,
			);
		}
	}
}
