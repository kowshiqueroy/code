<?php
/**
 * OVIJAT GROUP — index.php (Front Controller v2.0)
 */
session_start();
require_once __DIR__.'/includes/config.php';

$page=$_GET['page']??'home';

// Language Toggle
if($page==='lang'){
    $l=$_GET['l']??setting('default_lang','en');
    if(in_array($l,['en','bn'])) setcookie('ovijat_lang',$l,time()+(86400*365),'/','',false,false);
    redirect($_SERVER['HTTP_REFERER']??SITE_URL);
}

$routes=[
    'home'       =>'public/home.php',
    'products'   =>'public/products.php',
    'category'   =>'public/category.php',
    'product'    =>'public/product_detail.php',
    'concerns'   =>'public/concerns.php',
    'global'     =>'public/global.php',
    'rice'       =>'public/rice.php',
    'management' =>'public/management.php',
    'contact'    =>'public/contact.php',
    'careers'    =>'public/careers.php',
    'apply'      =>'public/apply.php',
];

$template=$routes[$page]??$routes['home'];
if(!file_exists(__DIR__.'/'.$template)) $template=$routes['home'];
require_once __DIR__.'/'.$template;
