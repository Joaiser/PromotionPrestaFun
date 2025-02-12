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
            return false;
        }

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

    private function isCustomerAlreadyRegistered($customerId)
    {
        $sql = 'SELECT COUNT(*) FROM '._DB_PREFIX_.'promotion_modal_log WHERE id_customer = '.(int)$customerId;
        return (bool)Db::getInstance()->getValue($sql);
    }

    private function insertPromotionLog($customerId, $orderId, $totalPrice, $defaultGroup, $firstname, $lastname)
    {
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

        return Db::getInstance()->execute($sql);
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
    
        // Verificar si hay un cliente conectado
        if (!$id_cliente) {
            return;
        }
    
        // Comprobar si el cliente ya está registrado
        if ($this->isCustomerAlreadyRegistered($id_cliente)) {
            return;
        }
    
        $cart = $context->cart;
    
        // Validar que el carrito esté cargado correctamente
        if (!Validate::isLoadedObject($cart)) {
            return;
        }
    
        $products = $cart->getProducts();
        $totalVentiladores = 0;
        $promotionCategoryId = 30;
    
        // Sumar solo los productos de la categoría de ventiladores
        foreach ($products as $product) {
            $id_product = (int) $product['id_product'];
            // Verificar si el producto pertenece a la categoría de ventiladores
            if ($this->isProductInCategory($id_product, $promotionCategoryId)) {
                $totalVentiladores += (float) $product['total']; // Sumar el total de los productos de la categoría
            }
        }
    
        // Verificar si el total de los productos de ventiladores es suficiente
        if ($totalVentiladores < 1200) {
            $this->removeCustomerFromGroup9($id_cliente);
            return;
        }
    
        // Añadir cliente al grupo 9 si cumple la condición
        $this->addCustomerToGroup9($id_cliente);
    }

    private function isProductInCategory($id_product, $id_category)
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_product` 
                WHERE `id_product` = ' . (int) $id_product . ' 
                AND `id_category` = ' . (int) $id_category;
        return (bool) Db::getInstance()->getValue($sql);
    }

    public function hookActionValidateOrder($params)
{
    if (!isset($params['order'])) {
        return;
    }

    $order = $params['order'];
    $customerId = $order->id_customer;
    $orderId = $order->id;

    // Verificar si el cliente ya está registrado
    if ($this->isCustomerAlreadyRegistered($customerId)) {
        return;
    }

    // Obtener el grupo por defecto del cliente
    $sqlGroup = 'SELECT id_default_group FROM '._DB_PREFIX_.'customer WHERE id_customer = '.(int)$customerId;
    $defaultGroup = Db::getInstance()->getValue($sqlGroup);

    // Obtener el carrito asociado a la orden
    $cart = new Cart($order->id_cart);
    if (!Validate::isLoadedObject($cart)) {
        return;
    }

    // Obtener los productos del carrito
    $products = $cart->getProducts();
    $promotionEligible = false;
    $totalPromotionProducts = 0;

    // Iterar sobre los productos y verificar las condiciones de la promoción
    foreach ($products as $product) {
        // Verificar si el producto pertenece a la categoría 30
        $categories = Product::getProductCategories($product['id_product']);
        if (in_array(30, $categories)) {
            // Verificar si la referencia del producto está en la lista de promociones
            if (in_array($product['reference'], self::PROMOTION_REFERENCES)) {
                $promotionEligible = true;
                $totalPromotionProducts += (float) $product['total']; // Sumar el total de los productos de la promoción
            }
        }
    }

    // Verificar si los productos promocionales suman el total necesario
    if ($promotionEligible && $totalPromotionProducts >= 1200) {
        // Obtener el total del carrito
        $totalPrice = (float) $cart->getOrderTotal(true, Cart::BOTH);

        if ($totalPrice >= 2000) {
            $this->insertPromotionLog($customerId, $orderId, $totalPrice, $defaultGroup, $this->context->customer->firstname, $this->context->customer->lastname);
            $this->context->cookie->__set('promotion_modal_message', '¡Felicidades! Has alcanzado las condiciones de la promoción de lote 16.');
            $this->context->cookie->__set('promotion_modal', true);
        } elseif ($totalPrice >= 1200) {
            $this->insertPromotionLog($customerId, $orderId, $totalPrice, $defaultGroup, $this->context->customer->firstname, $this->context->customer->lastname);
            $this->context->cookie->__set('promotion_modal_message', '¡Felicidades! Has alcanzado las condiciones de la promoción de lote 8.');
            $this->context->cookie->__set('promotion_modal', true);
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
            // Obtener el mensaje del modal (si existe)
            $message = $this->context->cookie->__get('promotion_modal_message');

            $this->context->smarty->assign('promotion_modal_message', $message);

            // Actualizar la columna `modal_shown` a 1 en la base de datos
            $customerId = $this->context->customer->id; // Obtener el ID del cliente actual.
            if ($customerId) {
                $this->updateModalShown($customerId);
            }

            // Incluir los archivos JS y CSS
            $this->context->controller->addJS($this->_path . 'views/js/modal.js');
            $this->context->controller->addCSS($this->_path . 'views/css/modal.css');

            // Limpiar las cookies de la promoción
            $this->context->cookie->__unset('promotion_modal');
            $this->context->cookie->__unset('promotion_modal_message');

            return $this->display(__FILE__, 'views/templates/hook/promotionModal.tpl');
        }

        return '';
    }
}
