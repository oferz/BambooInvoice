<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

	function formatCurrencySymbol($symbol_html_value, $is_value_hex=true)
	{
		if ($symbol_html_value != NULL)
		{
			$htmlSymbols = explode (',', $symbol_html_value);
			$symbol = ""; 
			foreach ($htmlSymbols as $htmlSymbol)
				$symbol .= "&#" . ($is_value_hex ? 'x' : '') . trim($htmlSymbol) . ";";
			
			return $symbol;
		} 
		else
		{
			return "";
		}
	}
	

?>