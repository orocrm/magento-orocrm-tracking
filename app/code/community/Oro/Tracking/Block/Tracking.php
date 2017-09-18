<?php
/**
 * Oro Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is published at http://opensource.org/licenses/osl-3.0.php.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magecore.com so we can send you a copy immediately
 *
 * @category  Oro
 * @package   Tracking
 * @copyright Copyright 2013 Oro Inc. (http://www.orocrm.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

/**
 * @method array getOrderIds()
 * @method void  setOrderIds(array $orderIds)
 */
class Oro_Tracking_Block_Tracking extends Mage_Core_Block_Template
{
    /**
     * Returns user identifier
     *
     * @return string
     */
    protected function _getUserIdentifier()
    {
        $session = Mage::getModel('customer/session');

        $data = array('id' => null, 'email' => null, 'visitor-id' => Mage::getSingleton('log/visitor')->getId());
        if ($session->isLoggedIn()) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = $session->getCustomer();
            $data     = array_merge(
                $data,
                array(
                    'id'    => $customer->getId(),
                    'email' => $customer->getEmail()
                )
            );
        } else {
            $data['id'] = Oro_Tracking_Helper_Data::GUEST_USER_IDENTIFIER;
        }

        return urldecode(http_build_query($data, '', '; '));
    }

    /**
     * Render information about specified orders
     *
     * @return string
     */
    protected function _getOrderEventsData()
    {
        $orderIds = $this->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return '';
        }

        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSearchFilter('entity_id', array('in' => $orderIds));

        $result = array();
        /** @var $order Mage_Sales_Model_Order */
        foreach ($collection as $order) {
            $result[] = sprintf(
                "_paq.push(['trackEvent', 'OroCRM', 'Tracking', '%s', '%s' ]);",
                Oro_Tracking_Helper_Data::EVENT_ORDER_PLACE_SUCCESS,
                $order->getIncrementId()
            );
        }

        return implode("\n", $result);
    }

    /**
     * Render information about cart on checkout index page
     *
     * @return string
     */
    protected function _getCheckoutEventsData()
    {
        /** @var $action Mage_Core_Controller_Varien_Action */
        $action          = Mage::app()->getFrontController()->getAction();
        $fullActionName  = $action->getFullActionName();
        $isCheckoutIndex = in_array(
            $fullActionName,
            array('checkout_onepage_index', 'checkout_multishipping_addresses')
        );

        if ($isCheckoutIndex) {
            /** @var $quote Mage_Sales_Model_Quote */
            $quote = Mage::getModel('checkout/session')->getQuote();

            //$quote->getOrigOrderId()

            return sprintf(
                "_paq.push(['trackEvent', 'OroCRM', 'Tracking', '%s', '%f' ]);",
                Oro_Tracking_Helper_Data::EVENT_CHECKOUT_STARTED,
                $quote->getSubtotal()
            );
        }

        return '';
    }

    /**
     * Render information about cart items added
     *
     * @return string
     */
    protected function _getCartEventsData()
    {
        $session = Mage::getSingleton('checkout/session');

        if ($session->hasData('justAddedProductId')) {
            $productId = $session->getData('justAddedProductId');
            $session->unsetData('justAddedProductId');

            return sprintf(
                "_paq.push(['trackEvent', 'OroCRM', 'Tracking', '%s', '%d' ]);",
                Oro_Tracking_Helper_Data::EVENT_CART_ITEM_ADDED,
                $productId
            );
        }

        return '';
    }

    /**
     * Renders information about event on register/login/logout
     *
     * @return string
     */
    protected function _getCustomerEventsData()
    {
        $result = array();

        $coreSession = Mage::getSingleton('core/session');
        if ($coreSession->getData('isJustRegistered')) {
            $coreSession->unsetData('isJustRegistered');

            $result[] = sprintf(
                "_paq.push(['trackEvent', 'OroCRM', 'Tracking', '%s', 1 ]);",
                Oro_Tracking_Helper_Data::EVENT_REGISTRATION_FINISHED
            );
        }

        if ($coreSession->getData('isJustLoggedIn')) {
            $customerSession = Mage::getModel('customer/session');
            if ($customerSession->isLoggedIn()) {
                $coreSession->unsetData('isJustLoggedIn');
                $result[] = sprintf(
                    "_paq.push(['trackEvent', 'OroCRM', 'Tracking', '%s', %d ]);",
                    Oro_Tracking_Helper_Data::EVENT_CUSTOMER_LOGIN,
                    $customerSession->getCustomerId()
                );
            }
        }

        if ($coreSession->getData('isJustLoggedOut')) {
            $result[] = sprintf(
                "_paq.push(['trackEvent', 'OroCRM', 'Tracking', '%s', %d ]);",
                Oro_Tracking_Helper_Data::EVENT_CUSTOMER_LOGOUT,
                $coreSession->getData('isJustLoggedOut')
            );
            $result[] = "_paq.push(['appendToTrackingUrl', 'new_visit=1']);";
            $result[] = "_paq.push(['deleteCookies']);";

            $coreSession->unsetData('isJustLoggedOut');
        }

        return empty($result) ? '' : implode("\n\r", $result);
    }

    /**
     * Render tracking scripts
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!Mage::helper('oro_tracking')->isEnabled()) {
            return '';
        }

        try {
            return parent::_toHtml();
        } catch (LogicException $e) {
            Mage::logException($e);

            return '';
        }
    }
}
