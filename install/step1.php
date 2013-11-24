<?if(!check_bitrix_sessid()) return;?>
<?
global $errors;
$module_id = 'onpay.sale';


if($errors!==false):
	for($i=0; $i<count($errors); $i++)
		$alErrors .= $errors[$i]."<br>";
	echo CAdminMessage::ShowMessage(Array("TYPE"=>"ERROR", "MESSAGE" =>GetMessage("MOD_INST_ERR"), "DETAILS"=>$alErrors, "HTML"=>true));
endif;
$arAllOptions = Array(
	Array("login", GetMessage("ONPAY.SALE_OPTIONS_LOGIN")." ", Array("text", ""), GetMessage("ONPAY.SALE_OPTIONS_LOGIN_DESC")),
	Array("api_in_key", GetMessage("ONPAY.SALE_OPTIONS_API_IN_KEY")." ", Array("text", 60), GetMessage("ONPAY.SALE_OPTIONS_API_IN_KEY_DESC")),
	Array("success_url", GetMessage("ONPAY.SALE_OPTIONS_SUCCESS_URL")." ", Array("text", 60), GetMessage("ONPAY.SALE_OPTIONS_SUCCESS_URL_DESC")),
	Array("fail_url", GetMessage("ONPAY.SALE_OPTIONS_FAIL_URL")." ", Array("text", 60), GetMessage("ONPAY.SALE_OPTIONS_FAIL_URL_DESC")),
	Array("iframe_form", GetMessage("ONPAY.SALE_OPTIONS_IFRAME_FORM")." ", Array("checkbox", 60), GetMessage("ONPAY.SALE_OPTIONS_IFRAME_FORM_DESC")),
	Array("convert", GetMessage("ONPAY.SALE_OPTIONS_CONVERT")." ", Array("checkbox", 60), GetMessage("ONPAY.SALE_OPTIONS_CONVERT_DESC")),
);
if(CModule::IncludeModule("currency")) {
	$lcur = CCurrency::GetList(($b="name"), ($order1="asc"), LANGUAGE_ID);
	while($lcur_res = $lcur->Fetch()) {
		$arAllOptions[] = Array("currency_".$lcur_res['CURRENCY'], GetMessage("ONPAY.SALE_OPTIONS_CURRENCY", array("#CURRENCY#"=>$lcur_res['CURRENCY']))." ", Array("currency"), GetMessage("ONPAY.SALE_OPTIONS_CURRENCY_DESC"));
	}
}
?>
<form action="<?echo $APPLICATION->GetCurPage()?>" name="form1">
<?=bitrix_sessid_post()?>
<input type="hidden" name="lang" value="<?=LANG?>">
<input type="hidden" name="id" value="<?=$module_id?>">
<input type="hidden" name="install" value="Y">
<input type="hidden" name="step" value="2">
<table cellpadding="3" cellspacing="0" border="0" width="0%">
<?	foreach($arAllOptions as $arOption):
		switch($arOption[0]) {
			case "currency_RUB":
			case "currency_RUR":
				$val = COption::GetOptionString($module_id, $arOption[0], 'WMR');
				break;
			case "currency_USD":
				$val = COption::GetOptionString($module_id, $arOption[0], 'WMZ');
				break;
			case "currency_EUR":
				$val = COption::GetOptionString($module_id, $arOption[0], 'WME');
				break;
			case "convert":
				$val = COption::GetOptionString($module_id, $arOption[0], 'Y');
				break;
			default:
				$val = COption::GetOptionString($module_id, $arOption[0]);
		}
		$type = $arOption[2];
	?>
		<tr>
			<td valign="top" width="50%"><?if($type[0]=="checkbox")
							echo "<p><label for=\"".htmlspecialchars($arOption[0])."\">".$arOption[1]."</label><br /><small>", $arOption[3], "</small></p>";
						else
							echo "<p><label for=\"id_install_public\">", $arOption[1], ":\n<br /><small>", $arOption[3], "</small></label></p>";?></td>
			<td valign="top" width="50%">
					<?if($type[0]=="checkbox"):?>
						<input type="checkbox" name="<?echo htmlspecialchars($arOption[0])?>" id="<?echo htmlspecialchars($arOption[0])?>" value="Y"<?if($val=="Y")echo" checked";?>>
					<?elseif($type[0]=="text"):?>
						<input type="text" size="<?echo $type[1]?>" maxlength="255" value="<?echo htmlspecialchars($val)?>" name="<?echo htmlspecialchars($arOption[0])?>">
					<?elseif($type[0]=="textarea"):?>
						<textarea rows="<?echo $type[1]?>" cols="<?echo $type[2]?>" name="<?echo htmlspecialchars($arOption[0])?>"><?echo htmlspecialchars($val)?></textarea>
					<?elseif($type[0]=="currency"):?>
						<select name="<?echo htmlspecialchars($arOption[0])?>"><option value=""><?=GetMessage("ONPAY.SALE_OPTIONS_CURRENCY_EMPTY")?></option>
							<?foreach(array('WMR', 'WMZ', 'WME', 'TST') as $currency):?> <option value="<?=$currency?>"<?=($val==$currency ? ' selected' : '')?>><?=$currency?></option> <?endforeach;?>
						</select>
					<?endif?>
			</td>
		</tr>
	<?endforeach?></table>
<p>
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="submit" name="" value="<?echo GetMessage("MOD_INSTALL")?>">	
</p>
<form>