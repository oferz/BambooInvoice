<?php
class currencies_model extends Model {

	function countAllCurrencies()
	{
		return $this->db->count_all('currencies');
	}

	// --------------------------------------------------------------------


	function getAllCurrencies()
	{
		$this->db->orderby('country', 'asc');

		return $this->db->get('currencies');
	}

	// --------------------------------------------------------------------

	function getCurrencyInfo($currency_code, $fields = '*')
	{
		$this->db->select($fields);
		$this->db->where('currency_code', $currency_code);
		
		return $this->db->get('currencies')->row();
	}

	// --------------------------------------------------------------------

	function getDefaultCurrency()
	{
		return $this->settings_model->get_setting('currency_type');
	}
	
	// --------------------------------------------------------------------

	
	function getCurrencySymbol($currency_code=NULL, $withSpanTags=TRUE)
	{
		$this->load->helper('currencies');
		
		$currencyInfo = $this->getCurrencyInfo($currency_code);
		if ($currencyInfo != NULL) 
		{
			$symbol = formatCurrencySymbol($currencyInfo->symbol_html_value, $currencyInfo->is_value_hex);
		} 
		else
		{
			$symbol = $this->settings_model->get_setting('currency_symbol');
		}
		
		if ($withSpanTags)
			return ('<span class="currency_symbol">' . $symbol . '</span>');
		else
			return $symbol;
	}

	// --------------------------------------------------------------------

	function addCurrency($currencyInfo)
	{
		$this->db->insert('currencies', $currencyInfo);

		return TRUE;
	}

	// --------------------------------------------------------------------

	function updateCurrency($currency_code, $currencyInfo)
	{
		$this->db->where('currency_code', $currency_code);
		$this->db->update('currencies', $currencyInfo);

		return TRUE;
	}

	// --------------------------------------------------------------------

	function deleteCurrency($currency_code)
	{
		$this->db->where('currency_code', $currency_code);
		$this->db->delete('currencies');
		
		return TRUE;
	}

}
?>