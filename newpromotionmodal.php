<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class NewPromotionModal extends Module
{
    public function __construct()
    {
        $this->name = 'newpromotionmodal';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Aitor';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('New Promotion Modal');
        $this->description = $this->l('Muestra un modal cuando un cliente haga un pedido que cumpla las condiciones de promoción');
    }

    public function install()
    {
        if (!parent::install() || 
            !$this->registerHook('actionValidateOrder') || 
            !$this->registerHook('displayHeader')) {
            error_log('Hook registration failed.');
            return false;
        }
        error_log('Hooks registered successfully.');
        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookActionValidateOrder($params)
{
    error_log('hookActionValidateOrder is triggered.');

    if (!isset($params['order'])) {
        error_log('No order parameter found.');
        return;
    }

    $order = $params['order'];
    $orderId = $order->id;
    $customerId = $order->id_customer;

    // Lista de referencias de productos para la promoción
    $promotionReferences = [
        'VT-ASPEN BM', 'VT-BARAT BM','VT-ASPEN NM', 'VT-BIRUJI BM', 'VT-BORA BM','VT-BORA NM','VT-DESTROY BM','VT-DESTROY GR','VT-DESTROY NM','VT-DESTROY OM', 'VT-DESTROY NK',
        'VT-FASHION BM', 'VT-GARBI BM','VT-GARBI CU/NG','VT-GARBI BM/NK','VT-GARBI NS/GR', 'VT-GREGAL BM','VT-GREGAL NM', 'VT-INDUS CLS', 'VT-INDUS LUZ',
        'VT-LEVANTE BLM','VT-LEVANTE NS','VT-LEVANTE CU','VT-LEVANTE NM','VT-LEVANTE B-NK', 'VT-LEVANTE CLS', 'VT-MANILA BM', 'VT-MDESTROY MSC', 'VT-MINIBORA BM','VT-MINIBORA NM',
        'VT-MINIDESTROY', 'VT-MINILEVANTE NS', 'VT-MINIMANILA', 'VT-MINISIROCOBM','VT-MINISIROCOCU','VT-MINISIROCONS',
        'VT-MISTRAL PL', 'VT-NICE BM', 'VT-OLAF BM', 'VT-SIROCO BM','VT-SIROCO CU','VT-SIROCO NS', 'VT-TERRAL BM','VT-TERRAL NM', 'VT-TUBIK', 'VT-TUBIK NM',
    ];

    // Consulta SQL para obtener el último pedido del cliente
    $sql = 'SELECT 
                od.id_order,
                SUM(od.product_price * od.product_quantity) AS total_price
            FROM '._DB_PREFIX_.'order_detail od
            JOIN '._DB_PREFIX_.'orders o ON od.id_order = o.id_order
            WHERE o.id_customer = '.(int)$customerId.' 
            AND od.product_reference IN ("'.implode('","', $promotionReferences).'")
            GROUP BY od.id_order
            ORDER BY o.date_add DESC
            LIMIT 1';

    error_log('Executing SQL: ' . $sql);

    // Ejecutar la consulta
    $result = Db::getInstance()->executeS($sql);

    if (!empty($result)) {
        $totalPrice = (float)$result[0]['total_price'];
        error_log('Total price: ' . $totalPrice);

        // Solo establece las cookies si el precio es mayor a 1200€ o 2000€
        if ($totalPrice > 2000) {
            $this->context->cookie->__set('promotion_modal_message', '¡Felicidades! Has alcanzado las condiciones de la promoción de lote 16.');
            $this->context->cookie->__set('promotion_modal', true);
        } elseif ($totalPrice > 1200) {
            $this->context->cookie->__set('promotion_modal_message', '¡Felicidades! Has alcanzado las condiciones de la promoción de lote 8.');
            $this->context->cookie->__set('promotion_modal', true);
        } else {
            // Si el precio es menor a 1200€, no hacer nada y no establecer cookies
            $this->context->cookie->__set('promotion_modal', false);
        }
    } else {
        // En caso de no obtener el total, no hacer nada
        $this->context->cookie->__set('promotion_modal', false);
    }
}


    public function hookDisplayHeader()
    {
        // Mostrar el modal solo si la cookie de la promoción está activa
        if ($this->context->cookie->__isset('promotion_modal') && $this->context->cookie->__get('promotion_modal')) {
            // Pasar el mensaje de promoción a la plantilla
            $message = $this->context->cookie->__get('promotion_modal_message');
            if ($message) {
                $this->context->smarty->assign('promotion_modal_message', $message);
            }

            // Incluir los archivos JS y CSS
            $this->context->controller->addJS($this->_path . 'views/js/modal.js');
            $this->context->controller->addCSS($this->_path . 'views/css/modal.css');

            return $this->display(__FILE__, 'views/templates/hook/promotionModal.tpl');
        }

        // Si no cumple las condiciones de la promoción, no mostrar nada de nada
        return '';
    }
}
