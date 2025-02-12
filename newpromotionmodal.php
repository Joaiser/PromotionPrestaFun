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
    if (!parent::install()
        || !$this->registerHook('actionCartSave')
        || !$this->registerHook('actionValidateOrder')
        || !$this->registerHook('displayHeader')) {
        error_log('Hook registration failed.');
        return false;
    }

    // Crear la tabla en la base de datos
    $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'promotion_modal_log` (
        `id_log` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_customer` INT UNSIGNED NOT NULL,
        `id_order` INT UNSIGNED NOT NULL,
        `order_total` DECIMAL(10, 2) NOT NULL,
        `last_group_default` INT UNSIGNED NOT NULL,
        `modal_shown` TINYINT(1) NOT NULL DEFAULT 0,
        `firstname` VARCHAR(255) NOT NULL,
        `lastname` VARCHAR(255) NOT NULL,
        `date_add` DATETIME NOT NULL
    ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

    if (!Db::getInstance()->execute($sql)) {
        error_log('Failed to create table.');
        return false;
    }

    error_log('Module installed successfully.');
    return true;
}


    public function uninstall()
    {
        $sql = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'promotion_modal_log`';
        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        return parent::uninstall();
    }

    private function isCustomerAlreadyRegistered($customerId)
    {
        $sql = 'SELECT COUNT(*) FROM '._DB_PREFIX_.'promotion_modal_log WHERE id_customer = '.(int)$customerId;
        return (bool)Db::getInstance()->getValue($sql);
    }

    private const PROMOTION_REFERENCES = [
        'VT-ASPEN BM', 'VT-BARAT BM', 'VT-ASPEN NM', 'VT-BIRUJI BM', 'VT-BORA BM', 'VT-BORA NM', 
        'VT-DESTROY BM', 'VT-DESTROY GR', 'VT-DESTROY NM', 'VT-DESTROY OM', 'VT-DESTROY NK', 
        'VT-FASHION BM', 'VT-GARBI BM', 'VT-GARBI CU/NG', 'VT-GARBI BM/NK', 'VT-GARBI NS/GR', 
        'VT-GREGAL BM', 'VT-GREGAL NM', 'VT-INDUS CLS', 'VT-INDUS LUZ', 'VT-LEVANTE BLM', 
        'VT-LEVANTE NS', 'VT-LEVANTE CU', 'VT-LEVANTE NM', 'VT-LEVANTE B-NK', 'VT-LEVANTE CLS', 
        'VT-MANILA BM', 'VT-MDESTROY MSC', 'VT-MINIBORA BM', 'VT-MINIBORA NM', 'VT-MINIDESTROY', 
        'VT-MINILEVANTE NS', 'VT-MINIMANILA', 'VT-MINISIROCOBM', 'VT-MINISIROCOCU', 'VT-MINISIROCONS', 
        'VT-MISTRAL PL', 'VT-NICE BM', 'VT-OLAF BM', 'VT-SIROCO BM', 'VT-SIROCO CU', 'VT-SIROCO NS', 
        'VT-TERRAL BM', 'VT-TERRAL NM', 'VT-TUBIK', 'VT-TUBIK NM',
    ];

    private function getOrderTotalForPromotion($customerId)
{
    $sql = 'SELECT 
                o.id_order,
                o.total_paid AS total_price
            FROM '._DB_PREFIX_.'orders o
            JOIN '._DB_PREFIX_.'order_detail od ON o.id_order = od.id_order
            WHERE o.id_customer = '.(int)$customerId.' 
            AND od.product_reference IN ("'.implode('","', self::PROMOTION_REFERENCES).'")
            AND o.current_state IN (2, 3, 5) -- Estados de pago aceptado, en preparación, enviado (ajusta según tu configuración)
            GROUP BY o.id_order
            ORDER BY o.date_add DESC
            LIMIT 1';

    return Db::getInstance()->executeS($sql);
}


    private function insertPromotionLog($customerId, $orderId, $totalPrice,$defaultGroup, $firstname, $lastname)
{
    // Insertar en la tabla con la nueva columna `last_group_default`
    $sqlInsert = 'INSERT INTO '._DB_PREFIX_.'promotion_modal_log 
                  (id_customer, id_order, order_total, last_group_default, modal_shown, firstname, lastname, date_add) 
                  VALUES (
                      '.(int)$customerId.', 
                      '.(int)$orderId.', 
                      '.(float)$totalPrice.', 
                      '.(int)$defaultGroup.',  
                      0, 
                      "'.pSQL($firstname).'", 
                      "'.pSQL($lastname).'", 
                      NOW()
                  )';

    Db::getInstance()->execute($sqlInsert);
}

public function addCustomerToGroup9($customerId)
{
    if (!$customerId) {
        return false;
    }

    $sql = 'INSERT INTO '._DB_PREFIX_.'customer_group (id_customer, id_group) 
            VALUES ('.(int)$customerId.', 9)';

    $result = Db::getInstance()->execute($sql);

    return $result;
}


public function removeCustomerFromGroup9($customerId)
{
    if (!$customerId) {
        return false;
    }

    $sql = 'DELETE FROM '._DB_PREFIX_.'customer_group 
            WHERE id_customer = '.(int)$customerId.' 
            AND id_group = 9';

    return Db::getInstance()->execute($sql);
}

public function hookActionCartSave($params)
{
    $context = Context::getContext();
    $id_cliente = $context->customer->id;


    if (!$id_cliente) {
        return;
    }

    if ($this->isCustomerAlreadyRegistered($id_cliente)) {
        return;
    }

    $cart = $context->cart;

    if (!Validate::isLoadedObject($cart)) {
        return;
    }

    // Obtener productos del carrito
    $products = $cart->getProducts();
    $totalCart = 0;

    // ID de la categoría de promoción
    $promotionCategoryId = 30;

    foreach ($products as $product) {
        $id_product = (int) $product['id_product'];

        // Verificar si el producto pertenece a la categoría de promoción
        if ($this->isProductInCategory($id_product, $promotionCategoryId)) {
            $totalCart = $cart->getOrderTotal(true, Cart::BOTH);
        }
    }


    // Si el total del carrito filtrado es menor a 1200€, el cliente se elimina del grupo 9
    if ($totalCart < 1200) {
        $this->removeCustomerFromGroup9($id_cliente);
        return;
    }

    // Si el total del carrito filtrado es mayor o igual a 1200€, el cliente se añade al grupo 9
    $this->addCustomerToGroup9($id_cliente);
}

private function isProductInCategory($id_product, $id_category)
{

    $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_product` 
            WHERE `id_product` = ' . (int) $id_product . ' 
            AND `id_category` = ' . (int) $id_category;
    $result = (bool) Db::getInstance()->getValue($sql);


    return $result;
}




public function hookActionValidateOrder($params)
{
    if (!isset($params['order'])) {
        return;
    }

    $order = $params['order'];
    $customerId = $order->id_customer;
    $orderId = $order->id;

    if ($this->isCustomerAlreadyRegistered($customerId)) {
        return;
    }

    // Obtener el id_default_group del cliente
    $sqlGroup = 'SELECT id_default_group FROM '._DB_PREFIX_.'customer WHERE id_customer = '.(int)$customerId;
    $defaultGroup = Db::getInstance()->getValue($sqlGroup);

    $result = $this->getOrderTotalForPromotion($customerId);

    if (!empty($result)) {
        $totalPrice = (float)$result[0]['total_price'];
        $firstname = $this->context->customer->firstname;
        $lastname = $this->context->customer->lastname;

        if ($totalPrice >= 2000) {
            $this->insertPromotionLog($customerId, $orderId, $totalPrice, $defaultGroup, $firstname, $lastname);
            $this->context->cookie->__set('promotion_modal_message', '¡Felicidades! Has alcanzado las condiciones de la promoción de lote 16.');
            $this->context->cookie->__set('promotion_modal', true);
        } elseif ($totalPrice >= 1200) {
            $this->insertPromotionLog($customerId, $orderId, $totalPrice, $defaultGroup, $firstname, $lastname);
            $this->context->cookie->__set('promotion_modal_message', '¡Felicidades! Has alcanzado las condiciones de la promoción de lote 8.');
            $this->context->cookie->__set('promotion_modal', true);
        } else {
            $this->context->cookie->__set('promotion_modal', false);
        }
    } else {
        $this->context->cookie->__set('promotion_modal', false);
    }
}


    private function updateModalShown($customerId)
{
    $sql = 'UPDATE '._DB_PREFIX_.'promotion_modal_log 
            SET modal_shown = 1 
            WHERE id_customer = '.(int)$customerId.' 
            AND modal_shown = 0'; // Solo actualizamos si aún no se ha mostrado.

    Db::getInstance()->execute($sql);
}

public function hookDisplayHeader()
{
    if ($this->context->cookie->__isset('promotion_modal') && $this->context->cookie->__get('promotion_modal')) {
        // Obtener el mensaje del modal (si existe).
        $message = $this->context->cookie->__get('promotion_modal_message');
        if ($message) {
            $this->context->smarty->assign('promotion_modal_message', $message);
        }

        // Actualizar la columna `modal_shown` a 1 en la base de datos.
        $customerId = $this->context->customer->id; // Obtener el ID del cliente actual.
        if ($customerId) {
            $this->updateModalShown($customerId);
        }

        // Incluir los archivos JS y CSS.
        $this->context->controller->addJS($this->_path . 'views/js/modal.js');
        $this->context->controller->addCSS($this->_path . 'views/css/modal.css');

        // Limpiar las cookies de la promoción.
        $this->context->cookie->__unset('promotion_modal');
        $this->context->cookie->__unset('promotion_modal_message');

        return $this->display(__FILE__, 'views/templates/hook/promotionModal.tpl');
    }

    return '';
}
}
