<?php
	$this->load->helper('currencies');

	if (!isset($currencies) or $currencies == NULL)
	{
		$currencies = $this->currencies_model->getAllCurrencies();
	}
	
	if (!isset($selected_currency) or ($selected_currency == NULL and !isset($with_system_default)))
	{
		$selected_currency = $this->currencies_model->getDefaultCurrency();
	}
	
	if (isset($with_system_default))
	{
		$system_currency = $this->currencies_model->getCurrencyInfo($this->currencies_model->getDefaultCurrency());
	}
?>


	<select size="1" class="currency_selector" id="currency_selector" name="<?php echo (isset($currency_selector_name)) ? $currency_selector_name : 'currency_code';?>">
<?php if (isset($with_system_default)): ?>
	<option value="" label="" <?php if ($selected_currency === NULL or $selected_currency === '') echo 'selected="selected"'; ?> >
		---- <?php echo $this->lang->line('clients_use_default_sys_currency'); ?> (<?php echo $system_currency->currency_name; ?>) ----
	</option>
<?php endif ?>

<?php foreach ($currencies->result() as $currency): ?>
	<option value="<?php echo $currency->currency_code; ?>" label="<?php echo formatCurrencySymbol($currency->symbol_html_value, $currency->is_value_hex); ?>" <?php if ($currency->currency_code == $selected_currency) echo 'selected="selected"'; ?> >
		<?php echo "{$currency->country} - {$currency->currency_name}"; ?>
	</option>
<?php endforeach ?>	
	</select>
	