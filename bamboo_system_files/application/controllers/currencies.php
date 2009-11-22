<?php

class Currencies extends MY_Controller {

	function Currencies()
	{
		parent::MY_Controller();
		$this->load->library('validation');
		$this->load->model('currencies_model');
	}

	// --------------------------------------------------------------------

	function index()
	{
		
	}

	// --------------------------------------------------------------------

}
?>