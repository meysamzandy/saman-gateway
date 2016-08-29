<?php
/*
Plugin Name:  درگاه بانک سامان ووکامرس
Plugin URI: http://newtheme.org
Description: افزودن درگاه پرداخت بانک سامان به فروشگاه ساز ووکامرس
Version: 1.0
Author: میثم زندی
Author URI: http://meysamzandy.ir
Tags: بانک سامان, saman
 */
require_once("nusoap.php");
add_action('plugins_loaded', 'woocommerce_saman_init', 0);

function woocommerce_saman_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	if($_GET['msg']!=''){
        add_action('the_content', 'showMessage_saman');
    }

    function showMessage_saman($content){
            return '<div class="box '.htmlentities($_GET['type']).'-box">'.base64_decode($_GET['msg']).'</div>'.$content;
    }
	
	class WC_Saman_Pay extends WC_Payment_Gateway {
	protected $msg = array();
        public function __construct(){
            // Go wild in here
            $this -> id = 'saman';
            $this -> method_title = __('&#1583;&#1585;&#1711;&#1575;&#1607; &#1662;&#1585;&#1583;&#1575;&#1582;&#1578; &#1576;&#1575;&#1606;&#1705; &#1587;&#1575;&#1605;&#1575;&#1606;', 'mezan');
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            $this -> merchant = $this -> settings['merchant'];	
			$this -> password = $this -> settings['password'];
            $this -> msg['reversal'] = "";
			$this -> msg['status'] = "";
			$this -> msg['message'] = "";
            $this -> msg['class'] = "";
            add_action( 'woocommerce_api_wc_saman_pay', array( $this, 'check_saman_response' ) );
            add_action('valid-saman-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_saman', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_saman',array($this, 'thankyou_page')); 
        }	
        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __(' فعال سازی/غیر فعال سازی ', 'mezan'),
                    'type' => 'checkbox',
                    'label' => __('  فعال سازی درگاه پرداخت سامان ', 'mezan'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __(' عنوان:', 'mezan'),
                    'type'=> 'text',
                    'description' => __(' عنوانی که کاربر در هنگام پرداخت مشاهده می کند ', 'mezan'),
                    'default' => __(' بانک سامان ', 'mezan')),
                'description' => array(
                    'title' => __(' توضیحات: ', 'mezan'),
                    'type' => 'textarea',
                    'description' => __('توضیحاتی که زیر لگوی بانک نوشته میشه', 'mezan'),
                    'default' => __(' پرداخت از طریق بانک سامان بوسیله کارت های عضو شتاب ', 'mezan')),
                'merchant' => array(
                    'title' => __('کد پذیرنده', 'mezan'),
                    'type' => 'text',
                    'description' => __('کد پذیرنده را وارد کنید')),
                'password' => array(
                    'title' => __('رمز پزیرنده در صورت نیاز', 'mezan'),
                    'type' => 'text',
                    'description' => __('رمز پذیرنده را وارد کنید')),
                'redirect_page_id' => array(
                    'title' => __(' صفحه بازگشت '),
                    'type' => 'select',
                    'options' => $this -> get_pages(' انتخاب برگه '),
                    'description' => " ادرس بازگشت از پرداخت در هنگام پرداخت "
                )
            );


        }		
        /**
         * تنظیمات پنل ادمین
         **/
        public function admin_options(){
            echo '<h3>'.__('&#1583;&#1585;&#1711;&#1575;&#1607; &#1662;&#1585;&#1583;&#1575;&#1582;&#1578; &#1576;&#1575;&#1606;&#1705; &#1587;&#1575;&#1605;&#1575;&#1606;', 'mezan').'</h3>';
            echo '<p>'.__('&#1583;&#1585;&#1711;&#1575;&#1607; &#1662;&#1585;&#1583;&#1575;&#1582;&#1578; &#1576;&#1575;&#1606;&#1705; &#1587;&#1575;&#1605;&#1575;&#1606;
&#1576;&#1585;&#1606;&#1575;&#1605;&#1607; &#1606;&#1608;&#1740;&#1587; &#1605;&#1740;&#1579;&#1605; &#1586;&#1606;&#1583;&#1740;
&#1587;&#1575;&#1604; &#1575;&#1580;&#1585;&#1575; 1393
').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * صفحه رسید
         **/
        function receipt_page($order){
            echo '<p>'.__('&#1575;&#1586; &#1587;&#1601;&#1575;&#1585;&#1588; &#1588;&#1605;&#1575; &#1605;&#1578;&#1588;&#1705;&#1585;&#1740;&#1605;. &#1576;&#1585;&#1575;&#1740; &#1662;&#1585;&#1583;&#1575;&#1582;&#1578; &#1608;&#1580;&#1607; &#1570;&#1606;&#1604;&#1575;&#1740;&#1606; &#1585;&#1608;&#1740; &#1583;&#1705;&#1605;&#1607; &#1586;&#1740;&#1585; &#1705;&#1604;&#1740;&#1705; &#1705;&#1606;&#1740;&#1583;', 'mezan').'</p>';
            echo $this -> generate_saman_form($order);
        }
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true )); 
        }
		 /**
         * بررسی صحت اطلاعات
         **/
       function check_saman_response(){
        global $woocommerce;
		$merchant = $this -> merchant;	
		$password = $this -> password;
		$ResNum	= $_POST['ResNum'];
		$RefNum	= $_POST['RefNum'];
		$State	= $_POST['State'];
		$order = new WC_Order($ResNum);
		if (isset($RefNum))
		{
			$soapclient = new nusoap_client('https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL ','wsdl');
			$soapProxy	= $soapclient->getProxy() ;
			$amount		= $soapProxy->VerifyTransaction($RefNum,$merchant);
			// پرداخت موفق بوده
			if (($amount>0) AND ($State=='OK'))
			{
				// مبلغ پرداختی با مبلغ ارسالی مقایسه میشود
				if ($order -> status !=='completed')
				{
					if($amount == $woocommerce->session->newtheme_id)
					{
						unset($woocommerce->session->newtheme_id);
						$this -> msg['status'] = 1;
						$this -> msg['message'] ='پرداخت با موفقیت انجام گردید.';
						$order -> payment_complete();
                        $order -> add_order_note('پرداخت انجام گردید<br/>کد رهگیری بانک: '.$verifySaleOrderId .' AND '.$verifySaleReferenceId );
                        $order -> add_order_note($this -> msg['message']);
                        $woocommerce -> cart -> empty_cart();
					}
					else
					{
						// وقتی که مقدار پرداختی با ارسالی برابر نباشد برگشت و خطا
						$res			= $soapProxy->ReverseTransaction($RefNum,$merchant,$password,$amount);
						$this -> msg['status']	= 0;
						$this -> msg['message']= 'مقدار پرداختی با مقدار ارسالی برابر نیست٬ مبلغ پرداختی به حساب شما بازگردانده خواهد شد.';
					}
				}
				else
				{
					// وقتی که سفارش قبلا پرداخت شده باشد
					$this -> msg['status']	= 0;
					$this -> msg['message']= 'این سفارش قبلا پرداخت شده است.';
				}
			}
			else
			{
				$this -> msg['status']	= 0;
				$this -> msg['message']= 'پرداخت تکمیل نشده است.';
			}
		}
		else
		{
			// وقتی که پرداخت ناقص باشد
			$this -> msg['status']	= 0;
			$this -> msg['message']= 'اطلاعات پرداخت کامل نیست.';
		}
			if ($this -> msg['status'] == 0){
				$order -> add_order_note($this -> msg['message']);
				$this -> msg['class']='error';
			}else{
				$this -> msg['class']='success';
			}
				$redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                //برای ووکامرسهای ورژن پایین تر از 2
				// نکته اینکه این اسکریپ توری نوشته شده تا در عین سادگی و سرعت با تمام ورژنهای ووکامرس در آینده هم کار کنه
                $redirect_url = add_query_arg( array('msg'=> base64_encode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
                exit;
		
}
        /**
         * دکمه بانک سامان رو ایجاد میکنه
         **/

      public function generate_saman_form($order_id){
            global $woocommerce;
            $order = &new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );	
		$merchant = $this -> merchant;	
		$password = $this -> password;
		//$orderId=rand(1, 9999999999999);
		unset($woocommerce->session->newtheme_id);
		$amount = str_replace(".00", "", $order -> order_total);
		$woocommerce->session->newtheme_id = $amount;
		$callBackUrl = $redirect_url;							
$send_atu="<script language='JavaScript' type='text/javascript'>
<!--
document.getElementById('checkout_confirmation').submit();
//-->
</script>";
echo '
		<form id="checkout_confirmation"  method="post" action="https://sep.shaparak.ir/Payment.aspx" style="margin:0px"  >
		<input type="hidden" id="Amount" name="Amount" value="'.$amount.'">
		<input type="hidden" id="MID" name="MID" value="'.$merchant.'">
		<input type="hidden" id="ResNum" name="ResNum" value="'.$order_id.'">
		<input type="hidden" id="RedirectURL" name="RedirectURL" value="'.$callBackUrl.'">
		<input type="submit" value="[جهت تایید و انتقال به درگاه بانکی کلید]"  />
		</form>' ;

        }
		// کانورت کدنوشته ها به فارسی
	private function western_to_persian($str) {
	$alphabet = array (
		'Û°' => '۰', 'Û±' => '۱', 'Û²' => '۲', 'Û³' => '۳', 'Û´' => '۴', 'Ûµ' => '۵', 'Û¶' => '۶', 'Û·' => '۷', 'Û¸' => '۸',
		'Û¹' => '۹', 'Ø¢' => 'آ', 'Ø§' => 'ا', 'Ø£' => 'أ', 'Ø¥' => 'إ', 'Ø¤' => 'ؤ', 'Ø¦' => 'ئ', 'Ø¡' => 'ء', 'Ø¨' => 'ب',
		'Ù¾' => 'پ', 'Øª' => 'ت', 'Ø«' => 'ث', 'Ø¬' => 'ج', 'Ú†' => 'چ', 'Ø­' => 'ح', 'Ø®' => 'خ', 'Ø¯' => 'د', 'Ø°' => 'ذ',
		'Ø±' => 'ر', 'Ø²' => 'ز', 'Ú˜' => 'ژ', 'Ø³' => 'س', 'Ø´' => 'ش', 'Øµ' => 'ص', 'Ø¶' => 'ض', 'Ø·' => 'ط', 'Ø¸' => 'ظ',
		'Ø¹' => 'ع', 'Øº' => 'غ', 'Ù' => 'ف', 'Ù‚' => 'ق', 'Ú©' => 'ک', 'Ú¯' => 'گ', 'Ù„' => 'ل', 'Ù…' => 'م', 'Ù†' => 'ن',
		'Ùˆ' => 'و', 'Ù‡' => 'ه', 'ÛŒ' => 'ی', 'ÙŠ' => 'ي', 'Û€' => 'ۀ', 'Ø©' => 'ة', 'ÙŽ' => 'َ', 'Ù' => 'ُ', 'Ù' => 'ِ',
		'Ù‘' => 'ّ', 'Ù‹' => 'ً', 'ÙŒ' => 'ٌ', 'Ù' => 'ٍ', 'ØŒ' => '،', 'Ø›' => '؛', ',' => ',', 'ØŸ' => '؟'
	);

	foreach($alphabet as $western => $fa)
		$str = str_replace($western, $fa, $str);

	return $str;
}
	private function CheckBPMStatus($ecode)	{
		               //$tmess=western_to_persian("Ø´Ø±Ø­ Ø®Ø·Ø§:");
		               switch ($ecode)
					     {
						  case 0:
					        $tmess=$this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Ø¨Ø§ Ù…ÙˆÙÙ‚ÙŠØª Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯");
						    break;
						  case 11:
					        $tmess=$this ->western_to_persian("Ø´Ù…Ø§Ø±Ù‡ Ú©Ø§Ø±Øª Ù…Ø¹ØªØ¨Ø± Ù†ÙŠØ³Øª");
						    break;
						  case 12:
					        $tmess= $this ->western_to_persian("Ù…ÙˆØ¬ÙˆØ¯ÙŠ Ú©Ø§ÙÙŠ Ù†ÙŠØ³Øª");
						    break;
						  case 13:
					        $tmess= $this ->western_to_persian("Ø±Ù…Ø² Ø¯ÙˆÙ… Ø´Ù…Ø§ ØµØ­ÙŠØ­ Ù†ÙŠØ³Øª");
						    break;
						  case 14:
					        $tmess= $this ->western_to_persian("Ø¯ÙØ¹Ø§Øª Ù…Ø¬Ø§Ø² ÙˆØ±ÙˆØ¯ Ø±Ù…Ø² Ø¨ÙŠØ´ Ø§Ø² Ø­Ø¯ Ø§Ø³Øª");
						    break;
						  case 15:
					        $tmess= $this ->western_to_persian("Ú©Ø§Ø±Øª Ù…Ø¹ØªØ¨Ø± Ù†ÙŠØ³Øª");
						    break;
						  case 16:
					        $tmess= $this ->western_to_persian("Ø¯ÙØ¹Ø§Øª Ø¨Ø±Ø¯Ø§Ø´Øª ÙˆØ¬Ù‡ Ø¨ÙŠØ´ Ø§Ø² Ø­Ø¯ Ù…Ø¬Ø§Ø² Ø§Ø³Øª");
						    break;
						  case 17:
					        $tmess= $this ->western_to_persian("Ú©Ø§Ø±Ø¨Ø± Ø§Ø² Ø§Ù†Ø¬Ø§Ù… ØªØ±Ø§Ú©Ù†Ø´ Ù…Ù†ØµØ±Ù Ø´Ø¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 18:
					        $tmess= $this ->western_to_persian("ØªØ§Ø±ÙŠØ® Ø§Ù†Ù‚Ø¶Ø§ÙŠ Ú©Ø§Ø±Øª Ú¯Ø°Ø´ØªÙ‡ Ø§Ø³Øª");
						    break;
						  case 19:
					        $tmess= $this ->western_to_persian("Ù…Ø¨Ù„Øº Ø¨Ø±Ø¯Ø§Ø´Øª ÙˆØ¬Ù‡ Ø¨ÙŠØ´ Ø§Ø² Ø­Ø¯ Ù…Ø¬Ø§Ø² Ø§Ø³Øª");
						    break;
						  case 111:
					        $tmess= $this ->western_to_persian("ØµØ§Ø¯Ø± Ú©Ù†Ù†Ø¯Ù‡ Ú©Ø§Ø±Øª Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 112:
					        $tmess= $this ->western_to_persian("Ø®Ø·Ø§ÙŠ Ø³ÙˆÙŠÙŠÚ† ØµØ§Ø¯Ø± Ú©Ù†Ù†Ø¯Ù‡ Ú©Ø§Ø±Øª");
						    break;
						  case 113:
					        $tmess= $this ->western_to_persian("Ù¾Ø§Ø³Ø®ÙŠ Ø§Ø² ØµØ§Ø¯Ø± Ú©Ù†Ù†Ø¯Ù‡ Ú©Ø§Ø±Øª Ø¯Ø±ÙŠØ§ÙØª Ù†Ø´Ø¯");
						    break;
						  case 114:
					        $tmess= $this ->western_to_persian("Ø¯Ø§Ø±Ù†Ø¯Ù‡ Ú©Ø§Ø±Øª Ù…Ø¬Ø§Ø² Ø¨Ù‡ Ø§Ù†Ø¬Ø§Ù… Ø§ÙŠÙ† ØªØ±Ø§Ú©Ù†Ø´ Ù†Ù…ÙŠ Ø¨Ø§Ø´Ø¯");
						    break;
						  case 21:
					        $tmess= $this ->western_to_persian("Ù¾Ø°ÙŠØ±Ù†Ø¯Ù‡ Ù…Ø¹ØªØ¨Ø± Ù†ÙŠØ³Øª");
						    break;
						  case 23:
					        $tmess= $this ->western_to_persian("Ø®Ø·Ø§ÙŠ Ø§Ù…Ù†ÙŠØªÙŠ Ø±Ø® Ø¯Ø§Ø¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 24:
					        $tmess= $this ->western_to_persian("Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ú©Ø§Ø±Ø¨Ø±ÙŠ Ù¾Ø°ÙŠØ±Ù†Ø¯Ù‡ Ù…Ø¹ØªØ¨Ø± Ù†ÙŠØ³Øª");
						    break;
						  case 25:
					        $tmess= $this ->western_to_persian("Ù…Ø¨Ù„Øº Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 31:
					        $tmess= $this ->western_to_persian("Ù¾Ø§Ø³Ø® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 32:
					        $tmess= $this ->western_to_persian("ÙØ±Ù…Øª Ø§Ø·Ù„Ø§Ø¹Ø§Øª ÙˆØ§Ø±Ø¯ Ø´Ø¯Ù‡ ØµØ­ÙŠØ­ Ù†ÙŠØ³Øª");
						    break;
						  case 33:
					        $tmess=$this ->western_to_persian("Ø­Ø³Ø§Ø¨ Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 34:
					        $tmess= $this ->western_to_persian("Ø®Ø·Ø§ÙŠ Ø³ÙŠØ³ØªÙ…ÙŠ");
						    break;
						  case 35:
					        $tmess= $this ->western_to_persian("ØªØ§Ø±ÙŠØ® Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 41:
					        $tmess= $this ->western_to_persian("Ø´Ù…Ø§Ø±Ù‡ Ø¯Ø±Ø®ÙˆØ§Ø³Øª ØªÚ©Ø±Ø§Ø±ÙŠ Ø§Ø³Øª");
						    break;
						  case 42:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Sale ÙŠØ§ÙØª Ù†Ø´Ø¯");
						    break;
						  case 43:
					        $tmess= $this ->western_to_persian("Ù‚Ø¨Ù„Ø§ Ø¯Ø±Ø®ÙˆØ§Ø³Øª Verify Ø¯Ø§Ø¯Ù‡ Ø´Ø¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 44:
					        $tmess= $this ->western_to_persian("Ø¯Ø±Ø®ÙˆØ§Ø³Øª Verify ÙŠØ§ÙØª Ù†Ø´Ø¯");
						    break;
						  case 45:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Settle Ø´Ø¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 46:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Settle Ù†Ø´Ø¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 47:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Settle ÙŠØ§ÙØª Ù†Ø´Ø¯");
						    break;
						  case 48:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Reverse Ø´Ø¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 49:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Refund ÙŠØ§ÙØª Ù†Ø´Ø¯");
						    break;
						  case 412:
					        $tmess= $this ->western_to_persian("Ø´Ù†Ø§Ø³Ù‡ Ù‚Ø¨Ø¶ Ù†Ø§Ø¯Ø±Ø³Øª Ø§Ø³Øª");
						    break;
						  case 413:
					        $tmess= $this ->western_to_persian("Ø´Ù†Ø§Ø³Ù‡ Ù¾Ø±Ø¯Ø§Ø®Øª Ù†Ø§Ø¯Ø±Ø³Øª Ø§Ø³Øª");
						    break;
						  case 414:
					        $tmess= $this ->western_to_persian("Ø³Ø§Ø²Ù…Ø§Ù† ØµØ§Ø¯Ø± Ú©Ù†Ù†Ø¯Ù‡ Ù‚Ø¨Ø¶ Ù…Ø¹ØªØ¨Ø± Ù†ÙŠØ³Øª");
						    break;
						  case 415:
					        $tmess= $this ->western_to_persian("Ø²Ù…Ø§Ù† Ø¬Ù„Ø³Ù‡ Ú©Ø§Ø±ÙŠ Ø¨Ù‡ Ù¾Ø§ÙŠØ§Ù† Ø±Ø³ÙŠØ¯Ù‡ Ø§Ø³Øª");
						    break;
						  case 416:
					        $tmess= $this ->western_to_persian("Ø®Ø·Ø§ Ø¯Ø± Ø«Ø¨Øª Ø§Ø·Ù„Ø§Ø¹Ø§Øª");
						    break;
						  case 417:
					        $tmess= $this ->western_to_persian("Ø´Ù†Ø§Ø³Ù‡ Ù¾Ø±Ø¯Ø§Ø®Øª Ú©Ù†Ù†Ø¯Ù‡ Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 418:
					        $tmess= $this ->western_to_persian("Ø§Ø´Ú©Ø§Ù„ Ø¯Ø± ØªØ¹Ø±ÙŠÙ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ù…Ø´ØªØ±ÙŠ");
						    break;
						  case 419:
					        $tmess= $this ->western_to_persian("ØªØ¹Ø¯Ø§Ø¯ Ø¯ÙØ¹Ø§Øª ÙˆØ±ÙˆØ¯ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ø¨ÙŠØ´ Ø§Ø² Ø­Ø¯ Ù…Ø¬Ø§Ø² Ø§Ø³Øª");
						    break;
						  case 421:
					        $tmess= $this ->western_to_persian("IP Ù…Ø¹ØªØ¨Ø± Ù†ÙŠØ³Øª");
						    break;
						  case 51:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ ØªÚ©Ø±Ø§Ø±ÙŠ Ø§Ø³Øª");
						    break;
						  case 54:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Ù…Ø±Ø¬Ø¹ Ù…ÙˆØ¬ÙˆØ¯ Ù†ÙŠØ³Øª");
						    break;
						  case 55:
					        $tmess= $this ->western_to_persian("ØªØ±Ø§Ú©Ù†Ø´ Ù†Ø§Ù…Ø¹ØªØ¨Ø± Ø§Ø³Øª");
						    break;
						  case 61:
					        $tmess= $this ->western_to_persian("Ø®Ø·Ø§ Ø¯Ø± ÙˆØ§Ø±ÙŠØ²");
						    break;
						 }
		return $tmess;
	}
	        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // افزودن به لیست صفحات
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

	}
    /**
     * افزودن درگاه به ووکامرس
     **/
    function woocommerce_add_saman_gateway($methods) {
        $methods[] = 'WC_Saman_Pay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_saman_gateway' );
	




		
}


?>