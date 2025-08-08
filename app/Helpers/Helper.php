<?php

use App\Models\GeneralSetting;

function invoiceThankYouMessage(){
   return $message = GeneralSetting::where('key', 'invoice_thank_you_message')->first()->value;
}