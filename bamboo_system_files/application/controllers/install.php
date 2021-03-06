<?php

// for convenient removal of this page from the online demo without needing to
// constantly remove and add this file.
// show_404();


// This install controller is only for quick insertion of an admin user into the system.
// I strongly recommend you delete this file after you've installed BambooInvoice.
// This controller is not in any way needed to run the application.

class Install extends Controller {

	function __construct()
	{
		parent::Controller();
		$this->load->library('encrypt');
		$this->load->dbutil();
	}

	// --------------------------------------------------------------------

	function index()
	{
		if ( $this->db->table_exists('settings') )
		{
			redirect('install/update_bamboo', 'refresh');
		}

		$this->load->helper('form', 'url');
		$this->load->library('validation');

		$rules['login_username']	= 'required|valid_email';
		$rules['login_password']	= 'required|matches[login_password_confirm]';
		$rules['login_password_confirm'] = 'required';
		$rules['primary_contact']	= 'required';

		$this->validation->set_rules($rules);

		$fields['login_username']	= $this->lang->line('login_username');
		$fields['login_password']	= $this->lang->line('login_password');
		$fields['login_password_confirm'] = $this->lang->line('login_password_confirm');
		$fields['primary_contact']	= $this->lang->line('settings_primary_contact');

		$this->validation->set_fields($fields);

		if ($this->validation->run() == FALSE)
		{
			$vars['message'] = '';
			$vars['page_title'] = $this->lang->line('install_install');

			$this->load->view('install_update/index', $vars);
		}
		else
		{
			$email = $this->input->post('login_username');
			$password = $this->input->post('login_password');
			$primary_contact = $this->input->post('primary_contact');

			$this->do_install($email, $password, $primary_contact);
		}
	}

	// --------------------------------------------------------------------

	function do_install($admin_email = '', $admin_password = '', $primary_contact = '')
	{
		// Just a note, the PHP version (at least 5) is already checked for in the main index.php file

		if ( ! extension_loaded('dom'))
		{
			show_error('BambooInvoice requires the DOM extension to be enabled to generate PDFs.  After you have satisfied this, you can try re-installing.');
		}

		if ( ! is_writable('invoices_temp'))
		{
			show_error('You need to set the invoices_temp folder to writable permissions or BambooInvoice will not be able to generate invoices.  After you have satisfied this, you can try re-installing.');
		}

		if ( ! isset($admin_password) || !isset($admin_email))
		{
			show_error("Please first define your admin login, email and password.  Instructions for this are located in the file /bamboo_system_files/application/controllers/install.php.   After you have satisfied this, you can try re-installing.");
		}

		if ($admin_email == '' OR $admin_password == '' OR $primary_contact == '')
		{
//			redirect('install', 'refresh'); // um... you shouldn't be here... but let's give this error instead
			show_error('something went wrong... no username or password');
		}

		$this->load->dbforge();

		// sessions_table
		$sessions_definition = array(
									'session_id' 			=> array('type' => 'VARCHAR', 'constraint' => 40, 'default' => 0),
									'ip_address' 			=> array('type' => 'VARCHAR', 'constraint' => 16, 'default' => 0),
									'user_agent' 			=> array('type' => 'VARCHAR', 'constraint' => 50, 'default' => ''),
									'last_activity'			=> array('type' => 'INT', 'constraint' => 10, 'unsigned' => TRUE, 'default' => 0),
									'user_id' 				=> array('type' =>'INT', 'constraint' => 11, 'default' => 0),
									'session_data'			=> array('type' =>'TEXT'),
									'logged_in' 			=> array('type' => 'INT', 'constraint' => 1, 'default' => 0)
									);

		$this->dbforge->add_field($sessions_definition);
		$this->dbforge->add_key('session_id', TRUE);
		$this->dbforge->add_key('ip_address', TRUE);
		$this->dbforge->create_table('sessions', TRUE);

		$clientcontacts_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'client_id' 			=> array('type' => 'INT', 'constraint' => 11),
									'first_name' 			=> array('type' => 'VARCHAR', 'constraint' => 25),
									'last_name' 			=> array('type' => 'VARCHAR', 'constraint' => 25),
									'title'		 			=> array('type' => 'VARCHAR', 'constraint' => 75),
									'email' 				=> array('type' => 'VARCHAR', 'constraint' => 50),
									'phone' 				=> array('type' => 'VARCHAR', 'constraint' => 20),
									'password' 				=> array('type' => 'VARCHAR', 'constraint' => 100),
									'access_level' 			=> array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
									'supervisor' 			=> array('type' => 'INT', 'constraint' => 11),
									'last_login' 			=> array('type' => 'INT', 'constraint' => 11),
									'password_reset'		=> array('type' => 'VARCHAR', 'constraint' => 12)
									);

		$this->dbforge->add_field($clientcontacts_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('clientcontacts', TRUE);
		$clients_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'name' 					=> array('type' => 'VARCHAR', 'constraint' => 75),
									'address1' 				=> array('type' => 'VARCHAR', 'constraint' => 100),
									'address2' 				=> array('type' => 'VARCHAR', 'constraint' => 100),
									'city' 					=> array('type' => 'VARCHAR', 'constraint' => 50),
									'province' 				=> array('type' => 'VARCHAR', 'constraint' => 25),
									'country' 				=> array('type' => 'VARCHAR', 'constraint' => 25),
									'postal_code' 			=> array('type' => 'VARCHAR', 'constraint' => 10),
									'website' 				=> array('type' => 'VARCHAR', 'constraint' => 150),
									'tax_status' 			=> array('type' => 'INT', 'constraint' => 1, 'default' => 1),
									);

		$this->dbforge->add_field($clients_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('clients', TRUE);

		$invoice_histories_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'invoice_id' 			=> array('type' => 'INT', 'constraint' => 11),
									'clientcontacts_id'		=> array('type' => 'VARCHAR', 'constraint' => 255),
									'date_sent' 				=> array('type' => 'DATE'),
									'contact_type' 			=> array('type' => 'INT', 'constraint' => 1),
									'email_body' 			=> array('type' => 'TEXT'),
									);

		$this->dbforge->add_field($invoice_histories_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('invoice_histories', TRUE);

		$invoice_payments_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'invoice_id' 			=> array('type' => 'INT', 'constraint' => 11),
									'date_paid' 				=> array('type' => 'DATE'),
									'amount_paid'			=> array('type' => 'FLOAT', 'constraint' => '7,2'),
									'payment_note' 			=> array('type' => 'VARCHAR', 'constraint' => 255)
									);

		$this->dbforge->add_field($invoice_payments_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('invoice_payments', TRUE);

		$invoices_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'client_id' 			=> array('type' => 'INT', 'constraint' => 11),
									'invoiceNumber' 		=> array('type' => 'VARCHAR', 'constraint' => 12),
									'dateIssued' 			=> array('type' => 'DATE'),
									'payment_term' 			=> array('type' => 'VARCHAR', 'constraint' => 50),
									'tax1_desc' 			=> array('type' => 'VARCHAR', 'constraint' => 50),
									'tax1_rate'				=> array('type' => 'DECIMAL', 'constraint' => '6,3'),
									'tax2_desc' 			=> array('type' => 'VARCHAR', 'constraint' => 50),
									'tax2_rate'				=> array('type' => 'DECIMAL', 'constraint' => '6,3'),
									'invoice_note' 			=> array('type' => 'VARCHAR', 'constraint' => 255)
									);

		$this->dbforge->add_field($invoices_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('invoices', TRUE);

		$invoice_items_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'invoice_id' 			=> array('type' => 'INT', 'constraint' => 11, 'default' => 0),
									'amount' 				=> array('type' => 'DECIMAL', 'constraint' => '7,2', 'default' => 0),
									'quantity' 				=> array('type' => 'DECIMAL', 'constraint' => '7,2', 'default' => 1),
									'work_description' 		=> array('type' => 'MEDIUMTEXT'),
									'taxable' 				=> array('type' => 'INT', 'constraint' => 1, 'default' => 1)
									);

		$this->dbforge->add_field($invoice_items_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('invoice_items', TRUE);

		$settings_definition = array(
									'id' 					=> array('type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE),
									'company_name'			=> array('type' => 'VARCHAR', 'constraint' => 75),
									'address1' 				=> array('type' => 'VARCHAR', 'constraint' => 100),
									'address2' 				=> array('type' => 'VARCHAR', 'constraint' => 100),
									'city' 					=> array('type' => 'VARCHAR', 'constraint' => 50),
									'province' 				=> array('type' => 'VARCHAR', 'constraint' => 25),
									'country' 				=> array('type' => 'VARCHAR', 'constraint' => 25),
									'postal_code' 			=> array('type' => 'VARCHAR', 'constraint' => 10),
									'website' 				=> array('type' => 'VARCHAR', 'constraint' => 150),
									'primary_contact' 		=> array('type' => 'VARCHAR', 'constraint' => 75),
									'primary_contact_email'	=> array('type' => 'VARCHAR', 'constraint' => 50),
									'logo' 					=> array('type' => 'VARCHAR', 'constraint' => 50),
									'logo_pdf' 				=> array('type' => 'VARCHAR', 'constraint' => 50),
									'invoice_note_default' 	=> array('type' => 'VARCHAR', 'constraint' => 255),
									'currency_type' 		=> array('type' => 'VARCHAR', 'constraint' => 20),
									'currency_symbol'		=> array('type' => 'VARCHAR', 'constraint' => 9, 'default' => '$'),
									'tax_code' 				=> array('type' => 'VARCHAR', 'constraint' => 50),
									'tax1_desc' 			=> array('type' => 'VARCHAR', 'constraint' => 50),
									'tax1_rate'				=> array('type' => 'FLOAT', 'constraint' => '6,3', 'default' => 0),
									'tax2_desc' 			=> array('type' => 'VARCHAR', 'constraint' => 50),
									'tax2_rate'				=> array('type' => 'FLOAT', 'constraint' => '6,3', 'default' => 0),
									'save_invoices' 		=> array('type' => 'CHAR', 'constraint' => 1, 'default' => 'n'),
									'days_payment_due' 		=> array('type' => 'INT', 'constraint' => 3, 'unsigned' => TRUE, 'default' => 30),
									'demo_flag' 			=> array('type' => 'CHAR', 'default' => 'n'),
									'display_branding' 		=> array('type' => 'CHAR', 'constraint' => 1, 'default' => 'y'),
									'bambooinvoice_version'	=> array('type' => 'VARCHAR', 'constraint' => 9)
									);

		$this->dbforge->add_field($settings_definition);
		$this->dbforge->add_key('id', TRUE);
		$this->dbforge->create_table('settings', TRUE);

		// Insert some starting data, username and password
		$this->db->set('id', 1);
		$this->db->set('client_id', 0);
		$this->db->set('email', $admin_email);
		$this->db->set('password', $this->encrypt->encode($admin_password));
		$this->db->set('last_login', time());
		$this->db->set('access_level', 1);
		$this->db->insert('clientcontacts');

		// Insert some default settings
		$this->db->set('bambooinvoice_version', '0.8.3');
		$this->db->set('id', 1);
		$this->db->set('primary_contact_email', $admin_email);
		$this->db->set('primary_contact', $primary_contact);
		$this->db->insert('settings');

		// Time to move onto the updates
		$this->session->sess_destroy(); // destroy any existing logins - MUWAH HA HA HA
		redirect('install/update_bamboo', 'refresh');
		exit('<strong>Not done yet!</strong> Now we need to update to the latest version of BambooInvoice.  Please follow this ' . anchor('install/update_bamboo', 'update link'));
	}

	// --------------------------------------------------------------------

	function update_bamboo()
	{
		$updates = '<h2>Updates</h2>';

		$updates .= '<ul><li>Base '.$this->lang->line('bambooinvoice_logo').' installed... attempting to update to most recent version</li>';

		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.0' OR $version == '0.8.1' OR $version == '0.8.2') 
		{
			show_error('Updating beyond this point requires a newer version of BambooInvoice.  Please contact Derek Allard if you want some guidance migrating your data.');
		}

		// regrab data, for the new update
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.3')
		{
			// this line is probably unneeded for most recent installs, but stragglers might benefit
			$this->dbforge->modify_column('invoice_items', array('quantity' => array('name' => 'quantity','type' => 'DECIMAL', 'constraint'=>'7,2', 'default'=>1)));
			$this->db->set('bambooinvoice_version', '0.8.4');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li>Upgrade to 0.8.4 success</li>";
		}

		// regrab data, for the new update
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.4')
		{
			// add client notes field
			$field = array(
							'client_notes' => array(
													'type' => 'MEDIUMTEXT'
												),
						);

			$this->dbforge->add_column('clients', $field);

			$clientcontacts_id = array(
									'clientcontacts_id' => array(
																	'name' => 'clientcontacts_id',
																	'type' => 'VARCHAR',
																	'constraint' => 255
															),
			);

			$this->dbforge->modify_column('invoice_histories', $clientcontacts_id);

			$this->db->set('bambooinvoice_version', '0.8.5');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li>Upgrade to 0.8.5 success</li>";
		}

		// regrab data, for the new update
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.5')
		{
			$field = array(
							'new_version_autocheck' => array(
																'type' => 'CHAR', 
																'default' => 'n'
															)
						);

			$this->dbforge->add_column('settings', $field);

			$this->db->set('bambooinvoice_version', '0.8.6');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li><strong>Please look for and delete</strong> the file(s) /bamboo_system_files/application/libraries/MY-Validation.php, and/or MY_old-Validation.php, if you are upgrading, as these are no longer used by Bamboo.</li>";
			$updates .= "<li>Upgrade to 0.8.6 success.</li>";
		}

		// regrab data, for the new update
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.6')
		{
			$fields = array(
									'invoiceNumber' => array(
																	'name' => 'invoice_number',
																	'type' => 'VARCHAR',
																	'constraint' => 255
															),
			);

			$this->dbforge->modify_column('invoices', $fields);

			$fields = array(
									'session_data' => array(
																	'name' => 'user_data',
																	'type' => 'TEXT'
															),
			);

			$this->dbforge->modify_column('sessions', $fields);

			$this->db->set('new_version_autocheck', 'y');
			$this->db->set('bambooinvoice_version', '0.8.7');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li>Upgrade to 0.8.7 success.</li>";
		}

		// regrab data, for the new update
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.7')
		{
			$bigger_email = array(
									'email' => array(
														'name' => 'email',
														'type' => 'VARCHAR',
														'constraint' => 127
													),
			);
			$this->dbforge->modify_column('clientcontacts', $bigger_email);

			// add client notes field
			$field = array(
							'logo_realpath' => array(
													'type' => 'CHAR',
													'constraint' => 1,
													'default' => 'n'
												),
						);
			$this->dbforge->add_column('settings', $field);

			// add client tax code field
			$field = array(
							'tax_code' => array(
													'type' => 'VARCHAR',
													'constraint' => 75,
													'default' => ''
												),
						);
			$this->dbforge->add_column('clients', $field);

			// add days invoice is due on a per invoice basis
			$this->load->model('settings_model');
			$field = array(
							'days_payment_due' 		=> array('type' => 'INT', 'constraint' => 3, 'unsigned' => TRUE, 'default' => $this->settings_model->get_setting('days_payment_due')),
						);
			$this->dbforge->add_column('invoices', $field);

			$this->db->set('bambooinvoice_version', '0.8.8');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li>Upgrade to 0.8.8 success.</li>";
		}

		// regrab data, for the new update
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.8')
		{
			$bigger_amount = array(
									'amount' => array(
														'name' => 'amount',
														'type' => 'DECIMAL',
														'constraint' => '11,2',
														'default' => 0
													),
			);
			$this->dbforge->modify_column('invoice_items', $bigger_amount);

			$bigger_note = array(
									'invoice_note' => array(
														'name' => 'invoice_note',
														'type' => 'TEXT',
														'constraint' => '2000'
													),
			);
			$this->dbforge->modify_column('invoices', $bigger_note);

			$this->db->set('bambooinvoice_version', '0.8.9');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li>Upgrade to 0.8.9 success.</li>";
		}

		// Update to version 0.8.9a
		$version = $this->db->get('settings')->row()->bambooinvoice_version;

		if ($version == '0.8.9')
		{
			// Create the currencies table
			$currencies_definition = array(
										'currency_code' 		=> array('type' => 'VARCHAR', 'constraint' => 3),
										'country' 				=> array('type' => 'VARCHAR', 'constraint' => 40),
										'currency_name' 		=> array('type' => 'VARCHAR', 'constraint' => 30),
										'is_value_hex' 			=> array('type' => 'TINYINT', 'constraint' => 1, 'default' => 1),
										'symbol_html_value' 	=> array('type' => 'VARCHAR', 'constraint' => 30)
										);

			$this->dbforge->add_field($currencies_definition);
			$this->dbforge->add_key('currency_code', TRUE);
			$this->dbforge->create_table('currencies', TRUE);
			
			// Add currencies to the currencies table
			$currencies = array(
				array('currency_code'=>'ALL', 'country'=>'Albania', 				'currency_name'=>'Leke', 							'is_value_hex'=>1, 'symbol_html_value'=>'4c, 65, 6b'),
				array('currency_code'=>'USD', 'country'=>'United States of America','currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'AFN', 'country'=>'Afghanistan', 			'currency_name'=>'Afghanis', 						'is_value_hex'=>1, 'symbol_html_value'=>'60b'),
				array('currency_code'=>'ARS', 'country'=>'Argentina', 				'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'AWG', 'country'=>'Aruba', 					'currency_name'=>'Guilders (also called Florins)', 	'is_value_hex'=>1, 'symbol_html_value'=>'192'),
				array('currency_code'=>'AUD', 'country'=>'Australia', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'AZN', 'country'=>'Azerbaijan', 				'currency_name'=>'New Manats', 						'is_value_hex'=>1, 'symbol_html_value'=>'43c, 430, 43d'),
				array('currency_code'=>'BSD', 'country'=>'Bahamas', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'BBD', 'country'=>'Barbados', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'BYR', 'country'=>'Belarus', 				'currency_name'=>'Rubles', 							'is_value_hex'=>1, 'symbol_html_value'=>'70, 2e'),
				array('currency_code'=>'EUR', 'country'=>'European Union (EU)', 	'currency_name'=>'Euro', 							'is_value_hex'=>0, 'symbol_html_value'=>'128'),
				array('currency_code'=>'BZD', 'country'=>'Belize', 					'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'42, 5a, 24'),
				array('currency_code'=>'BMD', 'country'=>'Bermuda', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'BOB', 'country'=>'Bolivia', 				'currency_name'=>'Bolivianos', 						'is_value_hex'=>1, 'symbol_html_value'=>'24, 62'),
				array('currency_code'=>'BAM', 'country'=>'Bosnia and Herzegovina', 	'currency_name'=>'Convertible Marka', 				'is_value_hex'=>1, 'symbol_html_value'=>'4b, 4d'),
				array('currency_code'=>'BWP', 'country'=>'Botswana', 				'currency_name'=>'Pulas', 							'is_value_hex'=>1, 'symbol_html_value'=>'50'),
				array('currency_code'=>'BGN', 'country'=>'Bulgaria', 				'currency_name'=>'Leva', 							'is_value_hex'=>1, 'symbol_html_value'=>'43b, 432'),
				array('currency_code'=>'BRL', 'country'=>'Brazil', 					'currency_name'=>'Reais', 							'is_value_hex'=>1, 'symbol_html_value'=>'52, 24'),
				array('currency_code'=>'GBP', 'country'=>'United Kingdom', 			'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'BND', 'country'=>'Brunei Darussalam', 		'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'KHR', 'country'=>'Cambodia', 				'currency_name'=>'Riels', 							'is_value_hex'=>1, 'symbol_html_value'=>'17db'),
				array('currency_code'=>'CAD', 'country'=>'Canada', 					'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'KYD', 'country'=>'Cayman Islands', 			'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'CLP', 'country'=>'Chile', 					'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'CNY', 'country'=>'China', 					'currency_name'=>'Yuan Renminbi', 					'is_value_hex'=>1, 'symbol_html_value'=>'a5'),
				array('currency_code'=>'COP', 'country'=>'Colombia', 				'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'CRC', 'country'=>'Costa Rica', 				'currency_name'=>'Col�n', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a1'),
				array('currency_code'=>'HRK', 'country'=>'Croatia', 				'currency_name'=>'Kuna', 							'is_value_hex'=>1, 'symbol_html_value'=>'6b, 6e'),
				array('currency_code'=>'CUP', 'country'=>'Cuba', 					'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'20b1'),
				array('currency_code'=>'CZK', 'country'=>'Czech Republic', 			'currency_name'=>'Koruny', 							'is_value_hex'=>1, 'symbol_html_value'=>'4b, 10d'),
				array('currency_code'=>'DKK', 'country'=>'Denmark', 				'currency_name'=>'Kroner', 							'is_value_hex'=>1, 'symbol_html_value'=>'6b, 72'),
				array('currency_code'=>'DOP', 'country'=>'Dominican Republic', 		'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'52, 44, 24'),
				array('currency_code'=>'XCD', 'country'=>'East Caribbean', 			'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'EGP', 'country'=>'Egypt', 					'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'SVC', 'country'=>'El Salvador', 			'currency_name'=>'Colones', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'EEK', 'country'=>'Estonia', 				'currency_name'=>'Krooni', 							'is_value_hex'=>1, 'symbol_html_value'=>'6b, 72'),
				array('currency_code'=>'GHC', 'country'=>'Ghana', 					'currency_name'=>'Cedis', 							'is_value_hex'=>1, 'symbol_html_value'=>'a2'),
				array('currency_code'=>'FKP', 'country'=>'Falkland Islands', 		'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'FJD', 'country'=>'Fiji', 					'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'GIP', 'country'=>'Gibraltar', 				'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'GTQ', 'country'=>'Guatemala', 				'currency_name'=>'Quetzales', 						'is_value_hex'=>1, 'symbol_html_value'=>'51'),
				array('currency_code'=>'GGP', 'country'=>'Guernsey', 				'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'GYD', 'country'=>'Guyana', 					'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'IMP', 'country'=>'Isle of Man', 			'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'HNL', 'country'=>'Honduras', 				'currency_name'=>'Lempiras', 						'is_value_hex'=>1, 'symbol_html_value'=>'4c'),
				array('currency_code'=>'HKD', 'country'=>'Hong Kong', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'HUF', 'country'=>'Hungary', 				'currency_name'=>'Forint', 							'is_value_hex'=>1, 'symbol_html_value'=>'46, 74'),
				array('currency_code'=>'ISK', 'country'=>'Iceland', 				'currency_name'=>'Kronur', 							'is_value_hex'=>1, 'symbol_html_value'=>'6b, 72'),
				array('currency_code'=>'INR', 'country'=>'India', 					'currency_name'=>'Rupees', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a8'),
				array('currency_code'=>'IDR', 'country'=>'Indonesia', 				'currency_name'=>'Rupiahs', 						'is_value_hex'=>1, 'symbol_html_value'=>'52, 70'),
				array('currency_code'=>'IRR', 'country'=>'Iran', 					'currency_name'=>'Rials', 							'is_value_hex'=>1, 'symbol_html_value'=>'fdfc'),
				array('currency_code'=>'ILS', 'country'=>'Israel', 					'currency_name'=>'New Shekels', 					'is_value_hex'=>1, 'symbol_html_value'=>'20aa'),
				array('currency_code'=>'JMD', 'country'=>'Jamaica', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'4a, 24'),
				array('currency_code'=>'JPY', 'country'=>'Japan', 					'currency_name'=>'Yen', 							'is_value_hex'=>1, 'symbol_html_value'=>'a5'),
				array('currency_code'=>'JEP', 'country'=>'Jersey', 					'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'KZT', 'country'=>'Kazakhstan', 				'currency_name'=>'Tenge', 							'is_value_hex'=>1, 'symbol_html_value'=>'43b, 432'),
				array('currency_code'=>'KPW', 'country'=>'North Korea', 			'currency_name'=>'Won', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a9'),
				array('currency_code'=>'KRW', 'country'=>'South Korea', 			'currency_name'=>'Won', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a9'),
				array('currency_code'=>'KGS', 'country'=>'Kyrgyzstan', 				'currency_name'=>'Soms', 							'is_value_hex'=>1, 'symbol_html_value'=>'43b, 432'),
				array('currency_code'=>'LAK', 'country'=>'Laos', 					'currency_name'=>'Kips', 							'is_value_hex'=>1, 'symbol_html_value'=>'20ad'),
				array('currency_code'=>'LVL', 'country'=>'Latvia', 					'currency_name'=>'Lati', 							'is_value_hex'=>1, 'symbol_html_value'=>'4c, 73'),
				array('currency_code'=>'LBP', 'country'=>'Lebanon', 				'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'LRD', 'country'=>'Liberia', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'CHF', 'country'=>'Switzerland', 			'currency_name'=>'Francs', 							'is_value_hex'=>1, 'symbol_html_value'=>'43, 48, 46'),
				array('currency_code'=>'LTL', 'country'=>'Lithuania', 				'currency_name'=>'Litai', 							'is_value_hex'=>1, 'symbol_html_value'=>'4c, 74'),
				array('currency_code'=>'MKD', 'country'=>'Macedonia', 				'currency_name'=>'Denars', 							'is_value_hex'=>1, 'symbol_html_value'=>'434, 435, 43d'),
				array('currency_code'=>'MYR', 'country'=>'Malaysia', 				'currency_name'=>'Ringgits', 						'is_value_hex'=>1, 'symbol_html_value'=>'52, 4d'),
				array('currency_code'=>'MUR', 'country'=>'Mauritius', 				'currency_name'=>'Rupees', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a8'),
				array('currency_code'=>'MXN', 'country'=>'Mexico', 					'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'MNT', 'country'=>'Mongolia', 				'currency_name'=>'Tugriks', 						'is_value_hex'=>1, 'symbol_html_value'=>'20ae'),
				array('currency_code'=>'MZN', 'country'=>'Mozambique', 				'currency_name'=>'Meticais', 						'is_value_hex'=>1, 'symbol_html_value'=>'4d, 54'),
				array('currency_code'=>'NAD', 'country'=>'Namibia', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'NPR', 'country'=>'Nepal', 					'currency_name'=>'Rupees', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a8'),
				array('currency_code'=>'ANG', 'country'=>'Netherlands Antilles', 	'currency_name'=>'Guilders (also called Florins)', 	'is_value_hex'=>1, 'symbol_html_value'=>'192'),
				array('currency_code'=>'SBD', 'country'=>'Solomon Islands', 		'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'NZD', 'country'=>'New Zealand', 			'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'NIO', 'country'=>'Nicaragua', 				'currency_name'=>'Cordobas', 						'is_value_hex'=>1, 'symbol_html_value'=>'43, 24'),
				array('currency_code'=>'NGN', 'country'=>'Nigeria', 				'currency_name'=>'Nairas', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a6'),
				array('currency_code'=>'NOK', 'country'=>'Norway', 					'currency_name'=>'Krone', 							'is_value_hex'=>1, 'symbol_html_value'=>'6b, 72'),
				array('currency_code'=>'OMR', 'country'=>'Oman', 					'currency_name'=>'Rials', 							'is_value_hex'=>1, 'symbol_html_value'=>'fdfc'),
				array('currency_code'=>'PKR', 'country'=>'Pakistan', 				'currency_name'=>'Rupees', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a8'),
				array('currency_code'=>'PAB', 'country'=>'Panama', 					'currency_name'=>'Balboa', 							'is_value_hex'=>1, 'symbol_html_value'=>'42, 2f, 2e'),
				array('currency_code'=>'PYG', 'country'=>'Paraguay', 				'currency_name'=>'Guarani', 						'is_value_hex'=>1, 'symbol_html_value'=>'47, 73'),
				array('currency_code'=>'PEN', 'country'=>'Peru', 					'currency_name'=>'Nuevos Soles', 					'is_value_hex'=>1, 'symbol_html_value'=>'53, 2f, 2e'),
				array('currency_code'=>'PHP', 'country'=>'Philippines', 			'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'50, 68, 70'),
				array('currency_code'=>'PLN', 'country'=>'Poland', 					'currency_name'=>'Zlotych', 						'is_value_hex'=>1, 'symbol_html_value'=>'7a, 142'),
				array('currency_code'=>'QAR', 'country'=>'Qatar', 					'currency_name'=>'Rials', 							'is_value_hex'=>1, 'symbol_html_value'=>'fdfc'),
				array('currency_code'=>'RON', 'country'=>'Romania', 				'currency_name'=>'New Lei', 						'is_value_hex'=>1, 'symbol_html_value'=>'6c, 65, 69'),
				array('currency_code'=>'RUB', 'country'=>'Russia', 					'currency_name'=>'Rubles', 							'is_value_hex'=>1, 'symbol_html_value'=>'440, 443, 431'),
				array('currency_code'=>'SHP', 'country'=>'Saint Helena', 			'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'SAR', 'country'=>'Saudi Arabia', 			'currency_name'=>'Riyals', 							'is_value_hex'=>1, 'symbol_html_value'=>'fdfc'),
				array('currency_code'=>'RSD', 'country'=>'Serbia', 					'currency_name'=>'Dinars', 							'is_value_hex'=>1, 'symbol_html_value'=>'414, 438, 43d, 2e'),
				array('currency_code'=>'SCR', 'country'=>'Seychelles', 				'currency_name'=>'Rupees', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a8'),
				array('currency_code'=>'SGD', 'country'=>'Singapore', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'SOS', 'country'=>'Somalia', 				'currency_name'=>'Shillings', 						'is_value_hex'=>1, 'symbol_html_value'=>'53'),
				array('currency_code'=>'ZAR', 'country'=>'South Africa', 			'currency_name'=>'Rand', 							'is_value_hex'=>1, 'symbol_html_value'=>'52'),
				array('currency_code'=>'LKR', 'country'=>'Sri Lanka', 				'currency_name'=>'Rupees', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a8'),
				array('currency_code'=>'SEK', 'country'=>'Sweden', 					'currency_name'=>'Kronor', 							'is_value_hex'=>1, 'symbol_html_value'=>'6b, 72'),
				array('currency_code'=>'SRD', 'country'=>'Suriname', 				'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'SYP', 'country'=>'Syria', 					'currency_name'=>'Pounds', 							'is_value_hex'=>1, 'symbol_html_value'=>'a3'),
				array('currency_code'=>'TWD', 'country'=>'Taiwan', 					'currency_name'=>'New Dollars', 					'is_value_hex'=>1, 'symbol_html_value'=>'4e, 54, 24'),
				array('currency_code'=>'THB', 'country'=>'Thailand', 				'currency_name'=>'Baht', 							'is_value_hex'=>1, 'symbol_html_value'=>'e3f'),
				array('currency_code'=>'TTD', 'country'=>'Trinidad and Tobago', 	'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'54, 54, 24'),
				array('currency_code'=>'TRY', 'country'=>'Turkey', 					'currency_name'=>'Lira', 							'is_value_hex'=>1, 'symbol_html_value'=>'54, 4c'),
				array('currency_code'=>'TRL', 'country'=>'Turkey', 					'currency_name'=>'Liras', 							'is_value_hex'=>1, 'symbol_html_value'=>'20a4'),
				array('currency_code'=>'TVD', 'country'=>'Tuvalu', 					'currency_name'=>'Dollars', 						'is_value_hex'=>1, 'symbol_html_value'=>'24'),
				array('currency_code'=>'UAH', 'country'=>'Ukraine', 				'currency_name'=>'Hryvnia', 						'is_value_hex'=>1, 'symbol_html_value'=>'20b4'),
				array('currency_code'=>'UYU', 'country'=>'Uruguay', 				'currency_name'=>'Pesos', 							'is_value_hex'=>1, 'symbol_html_value'=>'24, 55'),
				array('currency_code'=>'UZS', 'country'=>'Uzbekistan', 				'currency_name'=>'Sums', 							'is_value_hex'=>1, 'symbol_html_value'=>'43b, 432'),
				array('currency_code'=>'VEF', 'country'=>'Venezuela', 				'currency_name'=>'Bolivares Fuertes', 				'is_value_hex'=>1, 'symbol_html_value'=>'42, 73'),
				array('currency_code'=>'VND', 'country'=>'Vietnam', 				'currency_name'=>'Dong', 							'is_value_hex'=>1, 'symbol_html_value'=>'20ab'),
				array('currency_code'=>'YER', 'country'=>'Yemen', 					'currency_name'=>'Rials', 							'is_value_hex'=>1, 'symbol_html_value'=>'fdfc'),
				array('currency_code'=>'ZWD', 'country'=>'Zimbabwe', 				'currency_name'=>'Zimbabwe Dollars', 				'is_value_hex'=>1, 'symbol_html_value'=>'5a, 24')
			);
			foreach ($currencies as $currency) {
				foreach ($currency as $key=>$value)
					$this->db->set($key, $value);
				$this->db->insert('currencies');
			}
			
			// If no default currency is selected, set USD as the default.
			$defaultCurrency = $this->db->get('settings')->row()->currency_type;
			if ($defaultCurrency == NULL || $defaultCurrency == '') {
				$this->db->set('currency_type', 'USD');
				$this->db->where('id', 1);
				$this->db->update('settings');
			}
			
			
			// Add currency_code column to the clients table
			$field = array(
							'currency_code' 	=> array('type' => 'VARCHAR', 'constraint' => 3, 'default' => NULL),
						);
			$this->dbforge->add_column('clients', $field);
			
			// Add currency_code column to the invoices table
			$field = array(
							'currency_code' 	=> array('type' => 'VARCHAR', 'constraint' => 3, 'default' => NULL),
						);
			$this->dbforge->add_column('invoices', $field);

			
			// Update the version number
			$this->db->set('bambooinvoice_version', '0.8.9a');
			$this->db->where('id', 1);
			$this->db->update('settings');

			$updates .= "<li>Upgrade to 0.8.9a success.</li>";
		}

		$updates .= '</ul>';

		// everything's done now, let's optimize and then brag
		$this->load->dbutil();
		$this->dbutil->optimize_database();

		echo $this->load->view('install_update/install_header', '', TRUE);
		echo $updates;
		?>

			<hr />

			<h3><?php echo $this->lang->line('bambooinvoice_logo');?> is successfully installed</h3>
			<p>You got it, <?php echo $this->lang->line('bambooinvoice_logo');?> is all set up now.  You can <a href="<?php echo site_url('login');?>">login</a> with the username and password you set up.</p>
			<p>You are <strong>strongly</strong> encouraged to delete this file now.</p>
			<ul>
				<li>/bamboo_system_files/application/controllers/install.php</li>
			</ul>
			<p>Where to from here? Probably the first thing you'll want to do is visit "settings" and enter your personal information.  This way it'll be ready for your first invoice.</p>
			<p>Have a comment or request?  Try leaving a note on the <a href="http://forums.bambooinvoice.org"><?php echo $this->lang->line('bambooinvoice_logo');?> forums</a>, or if you want to contact me directly, always feel free to email me at info@bambooinvoice.org.  Tech support stuff is probably better on the forums though ;)</p>
			<p>If you discover that you're rich (the reporting features might just reveal that), consider <a href="<?php echo site_url('donate');?>">donating</a> a little bit to keep this project sustained.  Thanks!</p>
			<p>Happy invoicing!<br /><em>Derek Allard</em></p>

		<?php
		echo $this->load->view('install_update/install_footer', '', TRUE);
	}

	// --------------------------------------------------------------------

	function not_installed()
	{
		$this->load->helper('url');

		echo $this->load->view('install_update/install_header', '', TRUE);
		echo 'BambooInvoice does not appear to be installed. '.anchor ('install', 'You can install it now').'.';
		echo $this->load->view('install_update/install_footer', '', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	  * This function is here to help me clean up the demo from time to time.  I STRONGLY recommend you don't use
	  * it, as it WILL wipe out all your data and recovery will not be possible.  Don't do it man... DON'T DO IT!
	  */ 
/*
	function baleeted()
	{
		// get all tables
		$tables = $this->db->list_tables();

		foreach ($tables as $table)
		{
			// leave the settings table alone, but empty all others
			if (strpos($table, 'settings') === FALSE)
			{
				$table = str_replace($this->db->dbprefix, '', $table);
				$this->db->truncate($table); 
			}
		}

		// add the admin back in.
		$this->db->set('id', 1);
		$this->db->set('client_id', 0);
		$this->db->set('email', 'info@bambooinvoice.org');
		$this->db->set('password', $this->encrypt->encode('demo'));
		$this->db->set('last_login', time());
		$this->db->set('access_level', 1);
		$this->db->insert('clientcontacts');

		// update some of the setting information
		$settings = array(
							'company_name'			=> 'DerekAllard.com',
							'address1' 				=> '123 Address Street',
							'address2' 				=> '',
							'city' 					=> 'Toronto',
							'province' 				=> 'Ontario',
							'country' 				=> 'Canada',
							'postal_code' 			=> 'ABC123',
							'website' 				=> 'http://bambooinvoice.org',
							'primary_contact' 		=> 'Derek Allard',
							'primary_contact_email'	=> 'info@bambooinvoice.org',
							'logo' 					=> '',
							'logo_pdf' 				=> '',
							'invoice_note_default' 	=> 'Thanks for your business',
							'currency_type' 		=> 'CDN',
							'currency_symbol'		=> '$',
							'tax_code' 				=> '123456789',
							'tax1_desc' 			=> 'GST',
							'tax1_rate'				=> 5,
							'tax2_desc' 			=> '',
							'tax2_rate'				=> '',
							'save_invoices' 		=> 'n',
							'days_payment_due' 		=> 30,
							'demo_flag' 			=> 'y',
							'display_branding' 		=> 'y',
							'new_version_autocheck'	=> 'y'
							);

		$this->db->update('settings', $settings, "id = 1");

		echo "all done";
	}
*/

}
?>