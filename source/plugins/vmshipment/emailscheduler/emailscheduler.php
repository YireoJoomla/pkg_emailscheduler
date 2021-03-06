<?php
/**
 * EmailScheduler plugin - VirtueMart
 *
 * @author    Yireo (info@yireo.com)
 * @package   EmailScheduler
 * @copyright Copyright 2015
 * @license   GNU Public License
 * @link      http://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Import the parent class
require_once JPATH_SITE . '/administrator/components/com_emailscheduler/plugins/product.php';

/**
 * EmailScheduler plugin - VirtueMart
 */
class PlgVmShipmentEmailscheduler extends EmailschedulerPluginProduct
{
	/**
	 * Event "plgVmConfirmedOrder"
	 *
	 * @param VirtueMartCart $cart  VirtueMart shopping cart object
	 * @param array          $order Array containing all order information
	 *
	 * @return bool
	 */
	public function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
	{
		// Exit if there is no order ID
		if (empty($order['details']['BT']))
		{
			return true;
		}

		// Handle the order
		$this->handleOrder($order);
	}

	/**
	 * Event "plgVmOnUpdateOrderShipment"
	 *
	 * @param object $data             Object of order data being changed
	 * @param mixed  $old_order_status Old order status
	 *
	 * @return bool
	 */
	public function plgVmOnUpdateOrderShipment($data, $old_order_status)
	{
		// Exit if there is no order ID
		$orderId = $data->virtuemart_order_id;

        if (empty($orderId))
		{
			return true;
		}

		// Load the full order
		try
		{
			$orderModel = VmModel::getModel('orders');
			$order = $orderModel->getOrder($orderId);
		}
		catch (Exception $e)
		{
			$application = JFactory::getApplication();
			$application->enqueueMessage($e->getMessage(), 'error');

			return false;
		}

		// Exit if there is no order ID
		if (empty($order['details']['BT']))
		{
			return true;
		}

        // Merge current data with previous order
        $data = (array) $data;

        foreach ($data as $name => $value)
        {
            $order[$name] = $value;
        }

        // Handle the order
		$this->handleOrder($order);
	}

	/**
	 * Helper method to handle email triggers for this order
	 *
	 * @param object $order Order object
	 *
	 * @return bool
	 */
	private function handleOrder($order)
	{
		// Gather custom variables
		$customVariables = array();
		$customVariables['bt'] = $this->objectToArray($order['details']['BT']);
		$customVariables['st'] = $this->objectToArray($order['details']['ST']);
		$customVariables['products'] = $this->getProductsExtract($order);
		$customVariables['order'] = $this->getOrderExtract($order);
		$customVariables['customer'] = $this->getCustomerExtract($order);

		// Gather the product IDs and SKUs
		$productIds = array();
		$productSkus = array();

		foreach ($order['items'] as $product)
		{
			$productIds[] = $product->virtuemart_product_id;
			$productSkus[] = $product->order_item_sku;
		}

        // Determine the order status
        $orderStatus = null;

        if (isset($order['order_status']))
        {
            $orderStatus = $order['order_status'];
        }
        elseif (isset($order['details']['BT']->order_status))
        {
            $orderStatus = $order['details']['BT']->order_status;
        }

        // Get the triggers
		$triggers = $this->getTriggers(array('condition' => '%virtuemart3.product%'));

		if (empty($triggers))
		{
			return true;
		}

		// Loop through the triggers to find a match
		foreach ($triggers as $trigger)
		{
			$condition = $trigger->condition;

			$products = $condition['virtuemart3.product'];

			if (empty($products))
			{
				continue;
			}

			$trigger->params['variables'] = $customVariables;

			if (isset($condition['virtuemart3.order_status']))
			{
				$statuses = $condition['virtuemart3.order_status'];
			}
			else
			{
				$statuses = array();
			}

			$productFound = false;

			foreach ($products as $product)
			{
				if (!in_array($product, $productIds) && !in_array(strtolower($product), array('*', 'all')))
				{
					continue;
				}

				if (!empty($statuses) && !in_array($orderStatus, $statuses))
				{
					continue;
				}

				$productFound = true;
			}

			if ($productFound)
			{
				$trigger->actions['user_email'] = $order['details']['BT']->email;
				$this->doActions($trigger->actions, $trigger->params);
			}
		}

		return true;
	}


	/**
	 * Gather a simple extract of the VirtueMart order
	 *
	 * @param array $order
	 *
	 * @return array
	 */
	private function getOrderExtract($order)
	{
		$details = [];

		$details['id'] = $details->virtuemart_order_id;
		$details['status'] = $details->order_status;
		$details['created'] = $details->created_on;
		$details['order_number'] = $details->order_number;

        return $details;
	}

	/**
	 * Gather a simple extract of the VirtueMart customer
	 *
	 * @param array $order
	 *
	 * @return array
	 */
	private function getCustomerExtract($order)
	{
		$details = $order['details']['BT'];

		return array(
			'firstname' => $details->first_name,
			'lastname' => $details->last_name,
		);
	}

	/**
	 * Gather a simple array of the purchased VirtueMart products
	 *
	 * @param array $order
	 *
	 * @return array
	 */
	private function getProductsExtract($order)
	{
		$products = array();

		foreach ($order['items'] as $item)
		{
            $product = $this->objectToArray($item);

			$product['id'] = $item->virtuemart_product_id;
			$product['sku'] = $item->order_item_sku;
			$product['name'] = $item->order_item_name;

            $products[] = $product;
		}

		return $products;
	}
}
