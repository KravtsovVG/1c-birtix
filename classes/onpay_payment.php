<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
include(GetLangFileName(dirname(__FILE__)."/", "/onpay_payment.php"));

class COnpayPayment {
	static $module_id = "onpay.sale";
	static $pay_url = "http://secure.onpay.ru/pay/";
	static $logo_url = "http://onpay.ru/images/onpay_logo.gif";
	static $log_path = "/upload/.log.onpay.sale";
	static $currency = array('RUR', 'EUR', 'USD',
		'WMB', 'WME', 'WMR', 'WMU', 'WMZ', 
		'LIE', 'LIQ', 'LIU', 'LIZ',
		'MBZ', 'TST');
	static $form_design = array('2' => 'FORM_DEFAULT', '7' => 'DESIGN_N7', '8' => 'DESIGN_N8', '9' => 'MOBILE_FORM');
	static $_df_pay_mode = "fix";
	static $_df_form_id = "7";

	static function toFloat($sum) {
		$sum = floatval($sum);
		if (strpos($sum, ".")) {
			$sum = round($sum, 2);
		} else {
			$sum = $sum.".0";
		}
		return $sum;
	}
	
	static function GetWMCurrency($currency) {
		$arCurrency = array();
		if(CModule::IncludeModule("currency")) {
			$lcur = CCurrency::GetList(($b="name"), ($order1="asc"), LANGUAGE_ID);
			while($lcur_res = $lcur->Fetch()) {
				$arCurrency[$lcur_res['CURRENCY']] = COption::GetOptionString(COnpayPayment::$module_id, "currency_".$lcur_res['CURRENCY']);
			}
		}
		if(isset($arCurrency, $currency)) $currency = $arCurrency[$currency];
		return $currency;
	}
	
	static function GetLogin($login="") {
		return $login ? $login : COption::GetOptionString(COnpayPayment::$module_id, "login", "");
	}
	
	static function GetApiInKey($key="") {
		return $key ? $key : COption::GetOptionString(COnpayPayment::$module_id, "api_in_key", "");
	}
	
	static function GetSuccessUrl() {
		return COption::GetOptionString(COnpayPayment::$module_id, "success_url", "");
	}
	
	static function GetConvert() {
		return COption::GetOptionString(COnpayPayment::$module_id, "convert", "Y");
	}
	
	static function GetFormId() {
		return COption::GetOptionString(COnpayPayment::$module_id, "form_id", "7");
	}
	
	static function GetPriceFinal() {
		return COption::GetOptionString(COnpayPayment::$module_id, "price_final", "N");
	}
	
	static function GetLang() {
		return COption::GetOptionString(COnpayPayment::$module_id, "form_lang", false);
	}
	
	static function GetExtParams() {
		return COption::GetOptionString(COnpayPayment::$module_id, "ext_params", false);
	}
	
	static function GetWidthDebug() {
		return COption::GetOptionString(COnpayPayment::$module_id, "width_debug", false);
	}
	
	function SaveLog($data) {
		if(!isset($GLOBALS[COnpayPayment::$module_id]["width_debug"])) {
			$GLOBALS[COnpayPayment::$module_id]["width_debug"] = COnpayPayment::GetWidthDebug();
		}
		if($GLOBALS[COnpayPayment::$module_id]["width_debug"] == 'Y' ) {
			$log_name = $_SERVER['DOCUMENT_ROOT'].COnpayPayment::$log_path;
			if(!file_exists($log_name)) {
				mkdir($log_name);
				chmod($log_name, BX_DIR_PERMISSIONS);
			}
			$log_name .= "/".date('d').".log";
			$td = mktime(0, 0, 0, intval(date("m")), intval(date("d")), intval(date("Y")));
			$log_open = (file_exists($log_name) && filemtime($log_name) < $td) ? "w" : "a+";
			if($fh = fopen($log_name, $log_open)) {
				fwrite($fh, date("d.m.Y H:i:s")." ip:{$_SERVER['REMOTE_ADDR']} => http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n");
				if(is_array($data)) {
					$key = $data['key'] && in_array($data['type'], array('check', 'pay')) ? $data['key'] : false ;
					$str = serialize($data);
					if($key) {
						$str = str_replace($key, "#KEY#", $str);
					}
				} else {
					$str = $data;
				}
				fwrite($fh, $str."\n");
				fclose($fh);
				chmod($log_name, BX_FILE_PERMISSIONS);
			}
		}
	}
	
	function CheckOrderPayed($order_id) {
		$order_id = intval($order_id);
		$ret = false;
		
		if($order_id > 0 && CModule::IncludeModule("sale") && ($arOrder = CSaleOrder::GetByID($order_id))) {
			$ret = ($arOrder['PAYED'] == 'Y');
		}
		
		return $ret;
	}
	
	function CheckAction($request) {
		COnpayPayment::SaveLog($request);
		$check = array(
			'type' => 'check',
			'pay_for' => intval($request['pay_for']),
			'amount' => COnpayPayment::toFloat($request['order_amount']),
			'currency' => trim($request['order_currency']),
			'code' => 2,
			'key' => COnpayPayment::GetApiInKey(),
			);
		$text = "Error order_id: {$check['pay_for']}";
		$order_amount = floatval($request['order_amount']);
		if(COnpayPayment::_Validate($request) && CModule::IncludeModule("sale") && ($arOrder = CSaleOrder::GetByID($request['ORDER_ID']))) {
			COnpayPayment::SaveLog($arOrder);
			$needSum = floatval($arOrder['PRICE']) - floatval($arOrder['SUM_PAID']);
			$currency = COnpayPayment::GetWMCurrency($arOrder['CURRENCY']);
			if($arOrder['PAYED'] == 'N' && $needSum <= $order_amount && $currency == $check['currency']) {
				$check['code'] = 0;
				$text = "OK";
			}
		}
		$check['md5_string'] = implode(";", $check);
		$check['md5'] = strtoupper(md5($check['md5_string']));
		COnpayPayment::SaveLog($check);
		$out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<result>
<code>{$check['code']}</code>
<pay_for>{$check['pay_for']}</pay_for>
<comment>{$text}</comment>
<md5>{$check['md5']}</md5>
</result>";
		echo $out;
		COnpayPayment::SaveLog($out."\n\n");
	}
	
	function PayAction($request) {
		COnpayPayment::SaveLog($request);
		$_request = $request;
		$pay = $payOut = array(
			'type' => 'pay',
			'pay_for' => intval($request['pay_for']),
			'onpay_id' => intval($request['onpay_id']),
			'order_id' => intval($request['pay_for']),
			'amount' => COnpayPayment::toFloat($request['order_amount']),
			'currency' => trim($request['order_currency']),
			'code' => 3,
			'key' => COnpayPayment::GetApiInKey(),
			);
		unset($pay['code']);
		unset($pay['order_id']);
		$pay['md5_string'] = implode(";", $pay);
		$pay['md5'] = strtoupper(md5($pay['md5_string']));
		$order_amount = floatval($request['order_amount']);
		$text = "Error in parameters data";
		if(COnpayPayment::_Validate($request) && CModule::IncludeModule("sale")) {
			$text = "Cannot find any pay rows acording to this parameters: wrong payment";
			if($arOrder = CSaleOrder::GetByID($request['ORDER_ID'])) {
				COnpayPayment::SaveLog($arOrder);
				$needSum = floatval($arOrder['PRICE']) - floatval($arOrder['SUM_PAID']);
				$currency = COnpayPayment::GetWMCurrency($arOrder['CURRENCY']);
				if($arOrder['PAYED'] == 'N' && $needSum <= $order_amount && $currency == $pay['currency']) {
					if($pay['md5'] != $request['md5']) {
						$text = "Md5 signature is wrong";
						$payOut['code'] = 7;
					} else {
						$arFields = array(
							'PS_STATUS' => 'Y',
							'PS_STATUS_CODE' => 0,
							'PS_STATUS_DESCRIPTION' => 'OK',
							'PS_STATUS_MESSAGE' => '',
							'PS_SUM' => floatval($arOrder['PS_SUM']) + $order_amount,
							'PS_CURRENCY' => $pay['currency'],
							'PS_RESPONSE_DATE' => Date(CDatabase::DateFormatToPHP(CLang::GetDateFormat("FULL", LANG))),
							);
						foreach($_request as $key=>$val) $arFields['PS_STATUS_MESSAGE'] .= "{$key}:{$val};\n";
						COnpayPayment::SaveLog($arFields);
						if(CSaleOrder::Update($arOrder["ID"], $arFields) && CSaleOrder::PayOrder($arOrder["ID"], "Y")) {
							$payOut['code'] = 0;
							$text = "OK";
						} else {
							$text = "Error in mechant database queries: operation or balance tables error";
						}
					}
				}
			}
		}
		$payOut['md5_string'] = implode(";", $payOut);
		$payOut['md5'] = strtoupper(md5($payOut['md5_string']));
		COnpayPayment::SaveLog($pay);
		COnpayPayment::SaveLog($payOut);
		$out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<result>
<code>{$payOut['code']}</code>
<comment>{$text}</comment>
<onpay_id>{$payOut['onpay_id']}</onpay_id>
<pay_for>{$payOut['pay_for']}</pay_for>
<order_id>{$payOut['order_id']}</order_id>
<md5>{$payOut['md5']}</md5>
</result>";
		echo $out;
		COnpayPayment::SaveLog($out."\n\n");
	}
	
	static function _Validate(&$request) {
		$request['ORDER_ID'] = intval($request['pay_for']);
		if($request['type'] == 'check') {
			return ($request['ORDER_ID']>0);
		} elseif($request['type'] == 'pay') {
			$request['error'] = "";
			if (empty($request['onpay_id'])) {
				$request['error'] .= GetMessage("ONPAY.SALE_ORDER_EMPTY");
			} else {
				if (!is_numeric(intval($request['onpay_id']))) {
					$request['error'] .= GetMessage("ONPAY.SALE_NOT_NUMERIC");
				}
			}
			if (empty($request['order_amount'])) {
				$error .= GetMessage("ONPAY.SALE_SUM_EMPTY");
			} else {
				if (!is_numeric($request['order_amount'])) {
					$request['error'] .= GetMessage("ONPAY.SALE_NOT_NUMERIC");
				}
			}
			if (empty($request['balance_amount'])) {
				$request['error'] .= GetMessage("ONPAY.SALE_SUM_EMPTY");
			} else {
				if (!is_numeric(intval($request['balance_amount']))) {
					$request['error'] .= GetMessage("ONPAY.SALE_NOT_NUMERIC");
				}
			}
			if (empty($request['balance_currency'])) {
				$request['error'] .= GetMessage("ONPAY.SALE_CURRENCY_EMPTY");
			} else {
				if (strlen($request['balance_currency'])>4) {
					$request['error'] .= GetMessage("ONPAY.SALE_CURRENCY_LONG");
				}
			}
			if (empty($request['order_currency'])) {
				$request['error'] .= GetMessage("ONPAY.SALE_CURRENCY_EMPTY");
			} else {
				if (strlen($request['order_currency'])>4) {
					$request['error'] .= GetMessage("ONPAY.SALE_CURRENCY_LONG");
				}
			}
			if (empty($request['exchange_rate'])) {
				$request['error'] .= GetMessage("ONPAY.SALE_SUM_EMPTY");
			} else {
				if (!is_numeric($request['exchange_rate'])) {
					$request['error'] .= GetMessage("ONPAY.SALE_NOT_NUMERIC");
				}
			}
			return empty($request['error']);
		}
		return false;
	}

}

?>