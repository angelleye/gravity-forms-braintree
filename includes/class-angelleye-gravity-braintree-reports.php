<?php

/**
 * Class for manage braintree transaction reports.
 */
class AngelleyeGravityFormsBraintreeReports extends GFAddOn {

    protected $_version = '1.0';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'braintree-reports';
    protected $_path = __FILE__;
    protected $_full_path = __FILE__;
    protected $_url = 'https://www.gravityforms.com';
    protected $_title = 'Braintree Transaction Reports';
    protected $_short_title = 'Braintree Reports';

    private static $_instance;

    public array $default_heading = [];
    public array $default_heading_after = [];
    public string $transaction_dir = 'transactions_report';

    public string $date_format = 'm/d/Y H:i';

    /**
     * Manage Braintree Reports instance.
     *
     * @return self
     */
    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Class constructor for manage hooks and filter.
     */
    protected function __construct() {
        parent::__construct();

        $this->_url = GRAVITY_FORMS_BRAINTREE_ASSET_URL;

        $this->setup_headings();

        add_action( 'wp_ajax_angelleye_gform_braintree_generate_report', [$this, 'gform_braintree_generate_report']);
        add_action( 'wp_ajax_angelleye_gform_braintree_report_delete', [$this, 'gform_braintree_report_delete']);
    }

    /**
     * Check gravity form settings elements.
     *
     * @return false
     */
    public function has_deprecated_elements() {
        return false;
    }

    /**
     * Register Braintree Reports settings fields.
     *
     * @return array[]
     */
    public function plugin_settings_fields() {

        return [
            [
                'title' => $this->_title,
                'fields' => [
                    [
                        'name' => 'braintree_reports_details',
                        'type' => 'html',
                        'html' => [ $this, 'render_reports_html'],
                    ],
                ],
            ]
        ];
    }

    /**
     * Setup braintree reports csv heading fields.
     *
     * @return void
     */
    public function setup_headings() {

        $this->default_heading = apply_filters( 'angelleye_braintree_transaction_heading', [
            'Transaction_id' => 'id',
            'amount' => 'amount',
            'status' => 'status',
            'merchantAccountId' => 'merchantAccountId',
            'currencyCode' => 'currencyIsoCode',
        ]);

        $this->default_heading_after = apply_filters( 'angelleye_braintree_transaction_heading_after', [
            'full_name' => "full_name",
            'billing' => 'billing',
            'shipping' => 'shipping',
            'createdAt' => "createdAt",
        ]);
    }

    /**
     * Get Braintree Gateway.
     *
     * @return bool|\Braintree\Gateway
     */
    public static function getBraintreeGateway() {

        try {

            $gform_braintree    = new Plugify_GForm_Braintree();
            return $gform_braintree->getBraintreeGateway();
        } catch (Exception $exception) {

            return false;
        }
    }

    /**
     * Get merchant accounts lists.
     *
     * @return array[]|false
     */
    public function merchant_account_choices() {

        try {

            $gateway = self::getBraintreeGateway();

            if ( ! empty( $gateway ) ) {

                $merchant_accounts = [
                    [
                        'label' => esc_html__( 'Select Merchant Account ID', 'angelleye-gravity-forms-braintree' ),
                        'value' => ''
                    ]
                ];

                $subMerchantAccounts = $gateway->merchantAccount()->all();

                foreach ( $subMerchantAccounts as $account ) {

                    $account_id = !empty( $account->id ) ? $account->id : '';
                    $account_currency = !empty( $account->currencyIsoCode ) ? $account->currencyIsoCode : '';
                    $merchant_accounts[] = [
                        'label' => sprintf('%s - [%s]', $account_id, $account_currency),
                        'value' => $account_id
                    ];
                }

                return $merchant_accounts;
            }
        } catch (Exception $exception) {

            return false;
        }

        return false;
    }

    /**
     * Get transaction directory path.
     *
     * @param bool $is_baseurl
     * @return string
     */
    public function get_transaction_dir_path( $is_baseurl = false) {

        $upload_dir = wp_upload_dir();

        $directory_path = $upload_dir['basedir'] . '/'.$this->transaction_dir;
        $directory_url = $upload_dir['baseurl'] . '/'.$this->transaction_dir;

        if ( ! file_exists( $directory_path ) ) {
            wp_mkdir_p( $directory_path );
        }

        return $is_baseurl ? $directory_url : $directory_path;
    }

    /**
     * Get generated Braintree transaction reports csv files.
     *
     * @param array $args
     * @return array
     */
    public function get_transaction_report_files( $args = [] ) {

        $args = wp_parse_args( $args, [
            'per_page' => 20,
            'paged' => 1,
            'order_by' => 'start_date',
            'order' => 'DESC',
        ] );

        $transaction_dir_path = $this->get_transaction_dir_path();

        $report_files = [];
        if ( is_dir( $transaction_dir_path ) ) {

            $transaction_files = scandir( $transaction_dir_path );
            $transaction_files = !empty( $transaction_files ) ? array_diff( $transaction_files, ['..', '.','.DS_Store'] ) : [];

            foreach ($transaction_files as $transaction_file ) {

                $file_basename = basename( $transaction_file );
                $file_arr = !empty( $file_basename ) ? explode('_', $file_basename) : [];
                $start_date = !empty( $file_arr[0] ) ? wp_date($this->date_format, $file_arr[0]) : '';
                $end_date = !empty( $file_arr[1] ) ? wp_date($this->date_format, $file_arr[1]) : '';
                $merchant_id = '';
                if( !empty( $file_arr ) && count( $file_arr ) > 3 ) {
                    $merchant_id =  !empty( $file_arr[2] ) ? $file_arr[2] : '';
                }

                $report_files[]  = [
                    'file' => $transaction_file,
                    'start_date' => $start_date,
                    'end_date' =>  $end_date,
                    'merchant_id' => $merchant_id,
                ];
            }
        }

        usort($report_files, function ( $item_a, $item_b ) use ($args) {
            $order_by = !empty( $args['order_by'] ) ? $args['order_by'] : '';
            $order = !empty( $args['order'] ) ? $args['order'] : '';

            if( !empty( $order ) && strtolower( $order ) === 'desc') {

                if( !empty( $order_by ) && ( $order_by === 'start_date' || $order_by === 'end_date' ) ) {
                    $value = strtotime( $item_b[$order_by] ) - strtotime( $item_a[$order_by] );
                }  else {
                    $value = strcmp( $item_b[$order_by], $item_a[$order_by]);
                }

            } else {

                if( !empty( $order_by ) && ( $order_by === 'start_date' || $order_by === 'end_date' ) ) {
                    $value = strtotime( $item_a[$order_by] ) - strtotime( $item_b[$order_by] );
                }  else {
                    $value = strcmp( $item_a[$order_by], $item_b[$order_by]);
                }
            }

            return $value;
        });

        $per_page = !empty( $args['per_page'] ) ? $args['per_page'] : 1;
        $paged = !empty( $args['paged'] ) ? $args['paged'] : 1;
        $offset = 0;
        if( !empty( $paged > 1) ) {
            $offset  = $per_page * ( $paged - 1 );
        }

        $report_files = !empty( $report_files ) ? array_slice($report_files, $offset, $per_page) : [];

        return [
            'records' => $report_files,
            'total_records' => !empty( $transaction_files ) ? count( $transaction_files ) : 0,
            'current' => $paged,
            'per_page' => $per_page,
        ];
    }

    /**
     * Display Braintree reports html.
     *
     * @return void
     */
    public function render_reports_html() {

        if( !empty( $_GET['subview'] ) && $_GET['subview'] === $this->_slug && !empty( $_GET['report'] ) ) {
            $this->display_report_history_html();
        } else {
            $this->display_reports_html();
        }
    }

    /**
     * Display selected report history with table format.
     *
     * @return void
     */
    public function display_report_history_html() {
        $transaction_dir_path = $this->get_transaction_dir_path();
        $report_path = $transaction_dir_path.'/'.$_GET['report'];

        if ( file_exists( $report_path )  ) {

            if( filesize($report_path) > 0 ) {

                $file_basename = basename( $_GET['report'] );
                $file_arr = !empty( $file_basename ) ? explode('_', $file_basename) : [];
                $start_date = !empty( $file_arr[0] ) ? wp_date($this->date_format, $file_arr[0]) : '';
                $end_date = !empty( $file_arr[1] ) ? wp_date($this->date_format, $file_arr[1]) : '';
                $merchant_id = '';
                if( !empty( $file_arr ) && count( $file_arr ) > 3 ) {
                    $merchant_id =  !empty( $file_arr[2] ) ? $file_arr[2] : '';
                }

                $reports = fopen($report_path,"r");
                $download_url = $this->get_transaction_dir_path(true).'/'.$_GET['report'];
                ?>
                <div class="transaction-report-history-details">
                    <p class="file-name"><?php echo sprintf( __('<strong>File Name:</strong> %s', 'angelleye-gravity-forms-braintree'), $_GET['report']); ?> <a title="<?php esc_attr_e('Download Braintree transaction Report', 'angelleye-gravity-forms-braintree'); ?>" href="<?php echo esc_url($download_url); ?>" download class="report-actions download-report"><span class="dashicons dashicons-download"></span></a></p>
                    <p class="start-date"><?php echo sprintf( __('<strong>Start Date:</strong> %s', 'angelleye-gravity-forms-braintree'), $start_date); ?></p>
                    <p class="end-date"><?php echo sprintf( __('<strong>End Date:</strong> %s', 'angelleye-gravity-forms-braintree'), $end_date); ?></p>
                    <?php if(  !empty( $merchant_id ) ) { ?>
                        <p class="merchant-account-id"><?php echo sprintf( __('<strong>Merchant Account ID:</strong> %s', 'angelleye-gravity-forms-braintree'), $merchant_id); ?></p>
                    <?php } ?>
                </div>
                <div class="transaction-report-history-wrap">
                    <table class="gform-table gform-table--responsive">
                        <?php
                        $counter = 0;
                        while ( ( $report_data = fgetcsv ($reports) ) !== false ) {
                            ?>
                            <tr>
                                <?php
                                foreach ( $report_data as $report ) {
                                    if( $counter > 0 ) {
                                        ?>
                                        <td><?php echo !empty( $report ) ? esc_html($report) : '-'; ?></td>
                                        <?php
                                    } else {
                                        ?>
                                        <th scope="col"><?php echo esc_html($report); ?></th>
                                        <?php
                                    }
                                }
                                ?>
                            </tr>
                            <?php
                            $counter++;
                        }
                        ?>
                    </table>
                </div>
                <?php

                fclose($reports);
            } else {
                echo '<p>'.sprintf( __( "<strong>%s</strong> report file is exists but not data available."), esc_html( $_GET['report'] )).'</p>';
            }
        } else {
            echo '<p>'.sprintf( __( "<strong>%s</strong> report file is not exists."), esc_html( $_GET['report'] )).'</p>';
        }
    }

    /**
     * Display braintree reports filter and listing.
     *
     * @return void
     */
    public function display_reports_html() {

        $merchant_accounts = $this->merchant_account_choices();
        $report_files = $this->get_transaction_report_files([
            'paged' => !empty( $_GET['paged'] ) ? $_GET['paged'] : 1,
        ]);
        $report_records =  !empty( $report_files['records'])  ? $report_files['records'] : [];
        ?>
        <div class="transactions-report-wrap">
            <div class="notifications"></div>
            <div class="transactions-report-filter">
                <div class="field-row">
                    <div class="filed-col start-date">
                        <label for="start_date"><?php esc_html_e('Start Date','angelleye-gravity-forms-braintree'); ?></label>
                        <input type="text" name="start_date" id="start_date" class="regular-text datepicker" placeholder="<?php echo wp_date( $this->date_format ); ?>" autocomplete="off" />
                    </div>
                    <div class="filed-col end-date">
                        <label for="end_date"><?php esc_html_e('End Date','angelleye-gravity-forms-braintree'); ?></label>
                        <input type="text" name="end_date" id="end_date" class="regular-text datepicker" placeholder="<?php echo wp_date($this->date_format );?>" autocomplete="off" />
                    </div>
                    <div class="filed-col merchant-account-id">
                        <label for="merchant_account_id"><?php esc_html_e('Merchant Account ID','angelleye-gravity-forms-braintree'); ?></label>
                        <select name="merchant_account_id" id="merchant_account_id" class="regular-text">
                            <?php
                            if( !empty( $merchant_accounts ) && is_array( $merchant_accounts ) ) {
                                foreach ( $merchant_accounts as $key => $merchant_account ) {
                                    ?>
                                    <option value="<?php echo esc_attr($merchant_account['value']); ?>"><?php echo esc_html($merchant_account['label']); ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filed-col actions">
                        <button type="button" name="search_transactions" id="search_transactions" data-action="transactions_report" data-nonce="<?php echo wp_create_nonce('transactions_report_form'); ?>" class="button button-primary primary large"><?php esc_html_e('Submit','angelleye-gravity-forms-braintree'); ?></button>
                    </div>
                </div>
            </div>
            <div class="transactions-report-lists">
                <table class="gform-table gform-table--responsive">
                    <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Braintree Report Files', 'angelleye-gravity-forms-braintree') ?></th>
                        <th scope="col"><?php esc_html_e('Start Date', 'angelleye-gravity-forms-braintree') ?></th>
                        <th scope="col"><?php esc_html_e('End Date', 'angelleye-gravity-forms-braintree') ?></th>
                        <th scope="col"><?php esc_html_e('Merchant Account', 'angelleye-gravity-forms-braintree') ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'angelleye-gravity-forms-braintree') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    if( !empty( $report_records ) && is_array( $report_records )  ) {
                        foreach ( $report_records as  $key =>  $report_file ) {

                            if( !empty( $report_file['file'] ) )  {

                                $download_url = $this->get_transaction_dir_path(true).'/'.$report_file['file'];
                                $view_report_url = add_query_arg(
                                    [
                                            'page' => 'gf_settings',
                                            'subview' => $this->_slug,
                                            'report' => $report_file['file'],
                                    ],
                                    admin_url( 'admin.php'),
                                );
                                ?>
                                <tr class="row_<?php echo esc_attr( $key ); ?>">
                                    <td data-header="<?php esc_html_e('Braintree Report Files', 'angelleye-gravity-forms-braintree') ?>"><?php echo $report_file['file']; ?></td>
                                    <td data-header="<?php esc_html_e('Start Date', 'angelleye-gravity-forms-braintree') ?>"><?php echo !empty( $report_file['start_date'] ) ? esc_html( $report_file['start_date'] ) : '-'; ?></td>
                                    <td data-header="<?php esc_html_e('End Date', 'angelleye-gravity-forms-braintree') ?>"><?php echo !empty( $report_file['end_date'] ) ? esc_html( $report_file['end_date'] ) : '-'; ?></td>
                                    <td data-header="<?php esc_html_e('Merchant Account', 'angelleye-gravity-forms-braintree') ?>"><?php echo !empty( $report_file['merchant_id'] ) ? esc_html( $report_file['merchant_id'] ) : '-'; ?></td>
                                    <td data-header="<?php esc_html_e('Actions', 'angelleye-gravity-forms-braintree') ?>">
                                        <ul class="actions">
                                            <li><a href="<?php echo esc_url( $view_report_url ); ?>" class="report-actions view-report"><span class="dashicons dashicons-visibility"></span></a></li>
                                            <li><a title="<?php esc_attr_e('Download Braintree transaction Report', 'angelleye-gravity-forms-braintree'); ?>" href="<?php echo esc_url($download_url); ?>" download class="report-actions download-report"><span class="dashicons dashicons-download"></span></a></li>
                                            <li><a href="javascript:void(0);" data-file_name="<?php echo esc_attr($report_file['file']); ?>" data-ref_id="row_<?php echo esc_attr( $key ); ?>" data-transaction_dir="<?php echo $this->transaction_dir; ?>" class="report-actions delete-report"><span class="dashicons dashicons-trash"></span></a></li>
                                        </ul>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center;"><?php esc_html_e('Braintree Transaction Reports not found','angelleye-gravity-forms-braintree'); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
                <div class="filter-pagination"><?php echo $this->get_pagination($report_files); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Get braintree report page pagination.
     *
     * @param $args
     * @return string
     */
    public function get_pagination( $args = [] ) {

        $total_records = !empty( $args['total_records'] ) ? (int) $args['total_records']  : 0;
        $current = !empty( $args['current'] ) ? (int) $args['current']  : 0;
        $per_page = !empty( $args['per_page'] ) ? (int) $args['per_page']  : 0;
        $prev_title = !empty( $args['prev_title'] ) ? $args['prev_title']  : __("Previous", "angelleye-gravity-forms-braintree");
        $next_title = !empty( $args['next_title'] ) ? $args['next_title']  : __("Next", "angelleye-gravity-forms-braintree");

        $default_base_url = add_query_arg(
            [
                'page' => 'gf_settings',
                'subview' => $this->_slug,
            ],
            admin_url( 'admin.php'),
        );

        $base_url = !empty( $args['base_url'] ) ? $args['base_url']  : $default_base_url;

        $pages = round( $total_records / $per_page);

        $pagination = '';
        if(  !empty( $pages ) && $pages  > 1 ) {

            $pagination .='<ul class="pagination">';

            if( $current > 1 ) {

                $prev_page = add_query_arg( [
                    'paged' => $current - 1,
                ], $base_url);

                $pagination .= '<li class="page-item prev">';
                    $pagination .='<a href="'.esc_url( $prev_page ).'">'.$prev_title.'</a>';
                $pagination .= '</li>';
            }

            for ( $i = 1; $i <= $pages; $i++ ) {

                $item_url = add_query_arg( [
                        'paged' => $i,
                ], $base_url);

                $active = ( $i === $current  ) ? 'active' : '';
                $pagination .='<li class="page-item '.$active.'">';
                    $pagination  .= '<a href="'.esc_url( $item_url ).'">'.$i.'</a>';
                $pagination .='</li>';
            }

            if( $current < $pages ) {

                $next_page = add_query_arg( [
                    'paged' => $current + 1,
                ], $base_url);

                $pagination .= '<li class="page-item next">';
                $pagination .='<a href="'. esc_url($next_page) .'">'.$next_title.'</a>';
                $pagination .= '</li>';
            }

            $pagination .='</ul>';
        }

        return $pagination;
    }

    /**
     * Generate braintree report.
     *
     * @return void
     * @throws \Braintree\Exception\NotFound
     */
    public function gform_braintree_generate_report() {
        $status = false;
        $redirect_url =  '';
        $message = __( 'Something went wrong. Please try again.', 'angelleye-gravity-forms-braintree' );

        if( !empty( $_POST['data_action'] ) && $_POST['data_action'] === 'transactions_report' && !empty( $_POST['data_nonce'] ) && wp_verify_nonce( $_POST['data_nonce'], 'transactions_report_form') ) {

            $start_date = !empty( $_POST['start_date'] ) ? $_POST['start_date'] : '';
            $end_date = !empty( $_POST['end_date'] ) ? $_POST['end_date'] : '';
            $merchant_account_id = !empty( $_POST['merchant_account_id'] ) ? $_POST['merchant_account_id'] : '';

            $gateway = self::getBraintreeGateway();

            if ( ! empty( $gateway ) ) {

                $search_args = [];

                if( !empty( $start_date ) && !empty( $end_date ) ) {
                    $search_args[] = Braintree\TransactionSearch::createdAt()->between( esc_attr($start_date), esc_attr( $end_date ));
                }

                if( !empty( $merchant_account_id ) ) {
                    $search_args[] = Braintree\TransactionSearch::merchantAccountId()->is(esc_attr( $merchant_account_id ) );
                }

                if( !empty( $search_args ) && is_array( $search_args ) ) {

                    $transactions = $gateway->transaction()->search($search_args);

                    if( !empty( $transactions ) ) {

                        $filter_transactions = [];
                        $max_line_items = 0;
                        foreach ( $transactions as $transaction ) {
                            $id = $transaction->id;
                            $line_items = $gateway->transactionLineItem()->findAll($id);
                            $filter_transactions[] = $this->filter_transactions( $transaction, $line_items );
                            $line_items_count = !empty( $line_items ) ? count( $line_items ) : 0;
                            if( $line_items_count > $max_line_items ) {
                                $max_line_items = $line_items_count;
                            }
                        }

                        if( !empty( $filter_transactions ) ) {

                            $csv_file = $this->generate_transaction_csv( $filter_transactions, [
                                'max_line_items' => $max_line_items,
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'merchant_account_id' => $merchant_account_id,
                            ]);

                            $status = true;
                            $message = __( 'Braintree Transaction Report generated.', 'angelleye-gravity-forms-braintree' );

                            $redirect_url  = add_query_arg(
                                [
                                    'page' => 'gf_settings',
                                    'subview' => $this->_slug,
                                    'report' => $csv_file['file_name'],
                                ],
                                admin_url( 'admin.php'),
                            );
                        } else {
                            $message = sprintf(
                                __( 'Braintree Transactions not found between %s to %s date.', 'angelleye-gravity-forms-braintree' ),
                                $start_date,
                                $end_date
                            );
                        }
                    }

                } else {
                    $message = __( 'Start date & End date is required field.', 'angelleye-gravity-forms-braintree' );
                }
            }  else  {
                $message = __( 'Braintree account settings not proper. Please check and try again.', 'angelleye-gravity-forms-braintree' );
            }
        } else {
            $message = __( 'Something went wrong. filter nonce not verified.', 'angelleye-gravity-forms-braintree' );
        }

        wp_send_json([
            'status'=> $status,
            'message' => $message,
            'redirect_url' => $redirect_url
        ]);
    }

    /**
     * Delete selected braintree report.
     *
     * @return void
     */
    public function gform_braintree_report_delete() {

        $status = false;
        $message = __( 'Something went wrong. Please try again.', 'angelleye-gravity-forms-braintree' );

        $file_name = !empty( $_POST['file_name'] ) ? $_POST['file_name'] : '';
        $transaction_dir = !empty( $_POST['transaction_dir'] ) ? $_POST['transaction_dir'] : '';
        if( !empty( $file_name ) && !empty(  $transaction_dir ) ) {

            $upload_dir = wp_upload_dir();
            $directory_path = $upload_dir['basedir'] . '/'.$transaction_dir;
            if( is_dir( $directory_path ) ) {
                $file_path = $directory_path.'/'.$file_name;
                if( file_exists( $file_path ) ) {
                    unlink($file_path);
                    $status = true;
                } else {
                    $message = __( 'File is not exists.','angelleye-gravity-forms-braintree');
                }
            } else {
                $message = __( 'File directory is not exists.','angelleye-gravity-forms-braintree');
            }
        } else {
            $message = __( 'File or directory value not found.','angelleye-gravity-forms-braintree');
        }

        wp_send_json([
            'status'=> $status,
            'file_name'=> $file_name,
            'message' => $message,
        ]);
    }

    /**
     * Filter braintree transaction.
     *
     * @param array|object $transaction
     * @param array|object $line_items
     * @return array|mixed|null
     */
    public function filter_transactions( $transaction, $line_items ) {

        $response = [];

        if( empty( $transaction ) ) {

            return $response;
        }

        if( !empty( $this->default_heading ) ) {
            foreach ( $this->default_heading as $heading_key => $heading ) {
                $response[$heading_key] = $this->get_transaction_option_value( $heading, $transaction );
            }
        }

        if( !empty( $line_items ) && is_array( $line_items ) ) {

            foreach ( $line_items as $key => $line_item ) {
                $key_name = "line_{$key}_name";
                $key_amount = "line_{$key}_amount";
                $response[$key_name] = $line_item->name;
                $response[$key_amount] = $line_item->totalAmount;
            }
        }

        if( !empty( $this->default_heading_after ) ) {
            foreach ( $this->default_heading_after as $heading_key => $heading ) {

                $response[$heading_key] = $this->get_transaction_option_value( $heading, $transaction );
            }
        }

        return apply_filters( 'angelleye_braintree_filter_transaction', $response, $transaction, $line_items );
    }

    /**
     * Get braintree transaction value using heading.
     *
     * @param string $heading
     * @param array|object $transaction
     * @return mixed|null
     */
    public function get_transaction_option_value( $heading, $transaction ) {


        switch ( $heading ) {
            case "full_name":
                $first_name = !empty( $transaction->customer['firstName'] ) ? $transaction->customer['firstName'] :'';
                $last_name = !empty( $transaction->customer['lastName'] ) ? $transaction->customer['lastName'] :'';
                $transaction_value = trim( "{$first_name} {$last_name}" );
                break;
            case "billing":
            case "shipping":
                $address = !empty( $transaction->$heading ) ? $transaction->$heading : [];

                $display_address = [];
                if( !empty( $address['streetAddress'] ) ) {
                    $display_address[] = $address['streetAddress'];
                }

                if( !empty( $address['locality'] ) ) {
                    $display_address[] = $address['locality'];
                }

                if( !empty( $address['region'] ) ) {
                    $display_address[] = $address['region'];
                }

                if( !empty( $address['countryName'] ) ) {
                    $display_address[] = $address['countryName'];
                }

                if( !empty( $address['postalCode'] ) ) {
                    $display_address[] = $address['postalCode'];
                }

                $transaction_value = !empty( $display_address ) ? implode(',', $display_address) : '';
                break;
            case "createdAt":
                $created_at = !empty( $transaction->createdAt ) ? $transaction->createdAt : '';
                $transaction_value = !empty( $created_at->format('Y-m-d H:i:s') ) ? $created_at->format('Y-m-d H:i:s') : '';
                break;
            default:
                $transaction_value = !empty( $transaction->{$heading} ) ? $transaction->{$heading} : '';
        }

        return apply_filters( 'angelleye_braintree_transaction_option_value', $transaction_value, $heading, $transaction );
    }

    /**
     * Generate braintree transaction csv file.
     *
     * @param array $transactions
     * @param array $args
     * @return array
     */
    public function generate_transaction_csv( $transactions, $args = []) {

        $data = [];

        $csv_heading = array_keys( $this->default_heading );

        if( !empty( $args['max_line_items'] ) && $args['max_line_items'] > 0 ) {

            for ( $line = 0; $line < $args['max_line_items']; $line++ ) {
                $csv_heading[] = "line_{$line}_name";
                $csv_heading[] = "line_{$line}_amount";
            }
        }

        $heading_after = !empty( $this->default_heading_after ) ? array_keys( $this->default_heading_after ) : '';

        if( !empty( $heading_after ) ) {

            $csv_heading = array_merge( $csv_heading,  $heading_after );
        }

        $data[] = $csv_heading;

        if( !empty( $transactions ) && is_array( $transactions ) ) {

            foreach ( $transactions as $key => $transaction ) {

                $temp_data = [];
                foreach ( $csv_heading as $heading_key )  {
                    $temp_data[] = !empty( $transaction[$heading_key] ) ? $transaction[$heading_key] : '';
                }

                $data[] = $temp_data;
            }
        }

        $dir_path = $this->get_transaction_dir_path();
        $file_name = $this->get_transaction_csv_name( $args );

        $csv_file = fopen("{$dir_path}/{$file_name}", 'w');

        if ($csv_file !== false) {
            foreach ($data as $row) {
                fputcsv($csv_file, $row);
            }
            fclose($csv_file);
        }

        return [
            'file_name' => $file_name,
            'file_url' => $this->get_transaction_dir_path(true).'/'.$file_name,
        ];
    }

    /**
     * Get braintree transaction report csv file name.
     *
     * @param array $args
     * @return string
     */
    public function get_transaction_csv_name( $args ) {

        $csv_name = [];

        if( !empty( $args['start_date'] ) ) {
            $csv_name[] = strtotime( $args['start_date'] );
        }

        if( !empty( $args['end_date'] ) ) {
            $csv_name[] = strtotime( $args['end_date'] );
        }

        if( !empty( $args['merchant_account_id'] ) ) {
            $csv_name[] = $args['merchant_account_id'];
        }

        $csv_name[] = uniqid();

        return !empty( $csv_name ) ? implode('_',$csv_name).".csv" : '';
    }
}

AngelleyeGravityFormsBraintreeReports::get_instance();