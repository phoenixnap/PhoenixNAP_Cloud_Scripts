{php}
$userid = $this->_tpl_vars['clientsdetails']['id'];
$result = mysql_query("SELECT billingTotal FROM SC_Rated_Usage WHERE clientId=$userid ORDER BY lastBillingUpdate DESC LIMIT 1");
while ($row = mysql_fetch_row($result)) {
	$usage = $row[0];
}
$this->_tpl_vars['cloudUsageTotal'] = number_format((float)$usage, 2, '.', '');
{/php}

<table>
  <tr>
    <td class="fieldarea">Cloud Billing Total:</td>
    <td>${$cloudUsageTotal}</td>
  </tr>
</table>
<form method ="post" action="clientarea.php?action=productdetails">
<input type="hidden" name="id" value="{$serviceid}" />
<input type="hidden" name="modop" value="custom" />
<input type="hidden" name="a" value="getUpdatedBilling" />
<input type="submit" value="Update Cloud Billing" />
</form>
