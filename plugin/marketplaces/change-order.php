<?php
require __DIR__ . '/../../core/init.php';

if(!empty($_POST['status'])){
    if($_POST['status'] == 'complete') {
        $id = htmlentities($_POST['id']);
        $ecommerce->completeOrderTracking($id);
    }elseif($_POST['status'] == 'cancel'){
        $id = htmlentities($_POST['id']);
        $ecommerce->cancelOrder($id);
    }
}