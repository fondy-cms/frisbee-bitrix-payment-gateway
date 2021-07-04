<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) 
	die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if ($params["PAYED"] != "Y")
{
?>
	<table border="0" width="100%" cellpadding="2" cellspacing="2">
		<tr>
			<td align="center">
				<form action="<?=$params['URL']?>" method="post" id="frisbeePaymentForm">
					<input type="hidden" name="order_id" value="<?= htmlspecialcharsbx($params["ORDER_ID"]) ?>">
					<input type="hidden" name="merchant_id" value="<?= htmlspecialcharsbx($params["MERCHANT_ID"]) ?>">
					<input type="hidden" name="order_desc" value="<?= htmlspecialcharsbx($params["ORDER_DESC"]) ?>">
					<input type="hidden" name="amount" value="<?= htmlspecialcharsbx($params["AMOUNT"]) ?>">
					<input type="hidden" name="currency" value="<?= htmlspecialcharsbx($params["CURRENCY"]) ?>">
					<input type="hidden" name="server_callback_url" value="<?= htmlspecialcharsbx($params["SERVER_CALLBACK_URL"]) ?>">
					<input type="hidden" name="response_url" value="<?= htmlspecialcharsbx($params["RESPONSE_URL"]) ?>">
					<input type="hidden" name="lang" value="<?= htmlspecialcharsbx($params["LANG"]) ?>">
					<input type="hidden" name="sender_email" value="<?= htmlspecialcharsbx($params["SENDER_EMAIL"]) ?>">
					<input type="hidden" name="payment_systems" value="<?= htmlspecialcharsbx($params["PAYMENT_SYSTEMS"]) ?>">
					<input type="hidden" name="signature" value="<?= htmlspecialcharsbx($params["SIGNATURE"]) ?>">

					<input type="submit" value="<?=Loc::getMessage("FRISBEE_PAYMENT_BUTTON");?>">
				</form>
			</td>
		</tr>
	</table>
    <script>
        // document.getElementById('frisbeePaymentForm').submit()
    </script>
<?
}
else
{
	echo Loc::getMessage("FRISBEE_PAYMENT_PAID");
}
?>
