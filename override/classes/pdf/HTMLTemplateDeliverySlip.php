<?php
class HTMLTemplateDeliverySlip extends HTMLTemplateDeliverySlipCore
{
    public function __construct(OrderInvoice $order_invoice, $smarty, $bulk_mode = false)
    {
        parent::__construct($order_invoice, $smarty, $bulk_mode);
        $id_reliquat = Tools::getValue('id_reliquat');
        $reliquat = false;
        if ($id_reliquat > 0) {
            $this->order->delivery_number = $id_reliquat;
            
           /*  if ($_SERVER['REMOTE_ADDR']=='90.21.143.81') {
                echo $this->order->delivery_number;
                exit();
            } */
            $reliquat = true;
            $order_details = array();
            $order_details_old = Db::getInstance()->ExecuteS(
                '
                SELECT * FROM '._DB_PREFIX_.'ec_reliquat er
                LEFT JOIN '._DB_PREFIX_.'ec_reliquat_product erp ON (erp.id_reliquat = er.id_reliquat)
                WHERE er.id_reliquat = '.(int)$id_reliquat.'
                '
            );
            
            foreach ($order_details_old as $key => $od) {
                if ($key == 0) {
                    $this->date = $od['date_add'];
                }
                $order_details[$od['id_order_detail']] = $od;
            }
            $this->info_reliquat = $order_details;
            $this->id_reliquat = $id_reliquat;
            $prefix = Configuration::get('PS_DELIVERY_PREFIX', Context::getContext()->language->id);
            $this->title = sprintf(HTMLTemplateDeliverySlip::l('%1$s%2$06d'), $prefix, $id_reliquat);
        }
        $this->reliquat = $reliquat;
        
        
    }
    
    public function getContent()
    {
        if (!$this->reliquat) {
            return parent::getContent();
        }
        $delivery_address = new Address((int) $this->order->id_address_delivery);
        $formatted_delivery_address = AddressFormat::generateAddress($delivery_address, array(), '<br />', ' ');
        $formatted_invoice_address = '';
        if ($this->order->id_address_delivery != $this->order->id_address_invoice) {
            $invoice_address = new Address((int) $this->order->id_address_invoice);
            $formatted_invoice_address = AddressFormat::generateAddress($invoice_address, array(), '<br />', ' ');
        }
        $carrier = new Carrier($this->info_reliquat[array_keys($this->info_reliquat)[0] ]['id_carrier']);
        $carrier->name = ($carrier->name == '0' ? Configuration::get('PS_SHOP_NAME') : $carrier->name);
        $order_details = $this->order_invoice->getProducts();
        if (Configuration::get('PS_PDF_IMG_DELIVERY')) {
            foreach ($order_details as $key => &$order_detail) {
                if (!array_key_exists($order_detail['id_order_detail'], $this->info_reliquat)) {
                    unset($order_details[$key]);
                    continue;
                }
                $order_detail['product_quantity'] = $this->info_reliquat[$order_detail['id_order_detail']]['quantity'];
                if ($order_detail['image'] != null) {
                    $name = 'product_mini_' . (int) $order_detail['product_id'] . (isset($order_detail['product_attribute_id']) ? '_' . (int) $order_detail['product_attribute_id'] : '') . '.jpg';
                    $path = _PS_PROD_IMG_DIR_ . $order_detail['image']->getExistingImgPath() . '.jpg';
                    $order_detail['image_tag'] = preg_replace(
                        '/\.*' . preg_quote(__PS_BASE_URI__, '/') . '/',
                        _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR,
                        ImageManager::thumbnail($path, $name, 45, 'jpg', false),
                        1
                    );
                    if (file_exists(_PS_TMP_IMG_DIR_ . $name)) {
                        $order_detail['image_size'] = getimagesize(_PS_TMP_IMG_DIR_ . $name);
                    } else {
                        $order_detail['image_size'] = false;
                    }
                }
            }
        } else {
            foreach ($order_details as $key => &$order_detail) {
                if (!array_key_exists($order_detail['id_order_detail'], $this->info_reliquat)) {
                    unset($order_details[$key]);
                    continue;
                }
                $order_detail['product_quantity'] = $this->info_reliquat[$order_detail['id_order_detail']]['quantity'];
            }
        }
        $this->smarty->assign(array(
            'order' => $this->order,
            'order_details' => $order_details,
            'delivery_address' => $formatted_delivery_address,
            'invoice_address' => $formatted_invoice_address,
            'order_invoice' => $this->order_invoice,
            'carrier' => $carrier,
            'display_product_images' => Configuration::get('PS_PDF_IMG_DELIVERY'),
        ));
        $tpls = array(
            'style_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.addresses-tab')),
            'summary_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.summary-tab')),
            'product_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.product-tab')),
            'payment_tab' => $this->reliquat?'':$this->smarty->fetch($this->getTemplate('delivery-slip.payment-tab')),
        );
        $this->smarty->assign($tpls);
        return $this->smarty->fetch($this->getTemplate('delivery-slip'));
    }

    public function getFilename()
    {
        if ($this->reliquat) {
            return Configuration::get('PS_DELIVERY_PREFIX', Context::getContext()->language->id, null, $this->order->id_shop) . sprintf('%06d', $this->id_reliquat) . '.pdf';
        }
        return parent::getFilename();
        
    }
}
