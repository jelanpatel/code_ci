<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
//https://stripe.com/docs/api/php
require_once(__DIR__ . '/vendor/autoload.php');

class stripe {

    private $ci;
    public $config;
    public $actype;

    public function __construct($actype="") {
        $this->ci = & get_instance();
        $this->config = $this->ci->config->item('stripe');
        $actype = (isset($actype['type']) ? $actype['type'] : "");
        
        $mode = getenv('STRIPE_MODE');
        if($actype == 1){
            $this->config['secret_key'] = getenv('STRIPE_UP_SK_'.$mode);
            $this->config['publishable_key'] = getenv('STRIPE_UP_PK_'.$mode);
            $this->config['endpoint_secret'] = getenv('STRIPE_ENDPOINT_UP_SECRET_'.$mode);
        }else{
            $this->config['secret_key'] = getenv('STRIPE_SK_'.$mode);
            $this->config['publishable_key'] = getenv('STRIPE_PK_'.$mode);
            $this->config['endpoint_secret'] = getenv('STRIPE_ENDPOINT_SECRET_'.$mode);
        }

        $this->config['currency'] = getenv('STRIPE_CURRENCY');

        try {
            \Stripe\Stripe::setApiKey($this->config['secret_key']);
        } catch (\Stripe\Error\Authentication $e) {
            if ($mode == 'test') {
                $body = $e->getJsonBody();

                $err = $body['error'];

                print('Status is:' . $e->getHttpStatus() . "\n");
                print('Type is:' . $err['type'] . "\n");
                print('Code is:' . $err['code'] . "\n");
                // param is '' in this case
                print('Param is:' . $err['param'] . "\n");
                print('Message is:' . $err['message'] . "\n");
            }
        }
    }

    /**
     * [addCharge To charge a credit card, you create a charge object. If your API key is in test mode,
     *            the supplied payment source (e.g., card or Bitcoin receiver) won't actually be charged,
     *            though everything else will occur as if in live mode. (Stripe assumes that the charge
     *            would have completed successfully).]
     *
     * @param array $options [amount,
     *                        customer,
     *                        source(Stripe.js),
     *                        description,
     *                        metadata,
     *                        capture - Whether or not to immediately capture the charge. When false, the
     *                                  charge issues an authorization ,
     *                        statement_descriptor,
     *                        receipt_email,
     *                        destination (CONNECT ONLY, An account to make the charge on behalf of),
     *                        application_fee (CONNECT ONLY, A fee in cents that will be applied to the charge
     *                                         and transferred to the application owner's Stripe account.),
     *                        shipping default is { } )]
     */
    public function addCharge($options = []) {
        try {
            $options["currency"] = $this->config['currency'];
            return \Stripe\Charge::create($options);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     *
     * @param array $options = card [number,
     *                        exp_month,
     *                        exp_year,
     *                        cvc,
     *                        ]
     */
    public function createToken($options = []) {
        try {
            return \Stripe\Token::create($options);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [getCharge Retrieves the details of a charge that has previously been created. Supply the unique charge
     *            ID that was returned from your previous request, and Stripe will return the corresponding
     *            charge information. The same information is returned when creating or refunding the charge.]
     *
     * @param   $charge_id [ID stripe of charge]
     * @return             [stripe charge object]
     */
    public function getCharge($charge_id) {
        return \Stripe\Charge::retrieve($charge_id);
    }



    /**
     * [confirmCharge  charge the hold payment .]
     *
     * @param   $charge_id [ID stripe of charge]
     * @return             [stripe charge object]
     */
    public function confirmCharge($charge_id,$captureAmonut="") {
      
        try {
            $charge = $this->getCharge($charge_id);
            if($captureAmonut !=""){
                $charge->capture(['amount'=>$captureAmonut]);
            }else{
                $charge->capture();
            }
           
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [confirmCharge  charge the hold payment .]
     *
     * @param   $charge_id [ID stripe of charge]
     * @return             [stripe charge object]
     */
    public function cancelCharge($charge_id) {
      
        try {
            return $this->getCharge($charge_id)->refunds->create();
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [updateCharge Updates the specified charge by setting the values of the parameters passed. Any parameters
     *               not provided will be left unchanged.]
     *
     * @param   $charge_id [ID stripe of charge]
     * @param   $options   [description, metadata, receipt_emailand, fraud_details]
     * @return             [stripe charge object]
     */
    public function updateCharge($charge_id, $options) {
        $ch = $this->getCharge($charge_id);
        foreach ($options as $key => $value) {
            $ch->$key = $value;
        }
        return $ch->save();
    }

    /**
     * [captureCharge Capture the payment of an existing, uncaptured, charge. This is the second
     *                half of the two-step payment flow, where first you created a charge with
     *                the capture option set to false.]
     *
     * @param   $charge_id [ID stripe of charge]
     * @return             [stripe charge object]
     */
    public function captureCharge($charge_id) {
        return $this->getCharge($charge_id)->capture();
    }

    /**
     * [listCharges Returns a list of charges you've previously created. The charges are
     *             returned in sorted order, with the most recent charges appearing first.]
     *
     * @param  array  $options [  'created' A filter on the list based on the object created field. The value can be a
     *                                       string with an integer Unix timestamp, or
     *                              it can be a dictionary with the following options:
     *                                  child arguments
     *                                      'gt' where the created field is after this timestamp.
     *                                      'gte' where the created field is after or equal to this timestamp.
     *                                      'lt' where the created field is before this timestamp.
     *                                      'lte' where the created field is before or equal to this timestamp.
     *
     *                             'customer'
     *
     *                             'ending_before' is an object ID that defines your place in the list. For instance, if you
     *                                             make a list request and receive 100 objects, starting with obj_bar, your
     *                                             subsequent call can include ending_before=obj_bar in order to fetch the
     *                                             previous page of the list.
     *
     *                             'limit' default is 10, Limit can range between 1 and 100 items.
     *
     *                             'starting_after' starting_after is an object ID that defines your place in the list. For instance,
     *                                              if you make a list request and receive 100 objects, ending with obj_foo, your subsequent
     *                                              call can include starting_after=obj_foo in order to fetch the next page of the list.]
     *
     * @return           [A associative array with a data property that contains an array
     *                          of up to limit charges, starting after charge starting_after.]
     */
    public function listCharges($options = []) {
        return \Stripe\Charge::all($options);
    }

    /**
     * [addRefund description]
     * @param  $charge_id [Creating a new refund will refund a charge that has previously been created but not yet refunded.
     *                           Funds will be refunded to the credit or debit card that was originally charged. The fees you were
     *                           originally charged are also refunded.]
     *
     * @return  The refund object if the refund succeeded
     */
    public function addRefund($charge_id) {
        try {
            return $this->getCharge($charge_id)->refunds->create();
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
    public function addRefundAmount($options) {
        try {
            return \Stripe\Refund::create($options);
        } 
        catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [getRefund By default, you can see the 10 most recent refunds stored directly on the charge object, but you can also retrieve
     *             details about a specific refund stored on the charge.]
     *
     * @param   $charge_id [ID stripe of charge]
     * @param   $refund_id [ID stripe of refund]
     *
     * @return             [The refund object]
     */
    public function getRefund($charge_id, $refund_id) {
        try {
            return $this->getCharge($charge_id)->refunds->retrieve($refund_id);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [updateRefund Updates the specified refund by setting the values of the parameters passed.
     *               Any parameters not provided will be left unchanged.]
     *
     * @param   $charge_id [ID stripe of charge]
     * @param   $refund_id [ID stripe of refund]
     *
     * @param   $options   [metadata]
     *
     * @return             [The refund object]
     */
    public function updateRefund($charge_id, $refund_id, $options) {
        $re = $this->getRefund($charge_id, $refund_id);
        foreach ($options as $key => $value) {
            $re->$key = $value;
        }
        return $re->save();
    }

    /**
     * [listRefunds You can see a list of the refunds belonging to a specific charge. Note that the 10
     *              most recent refunds are always available by default on the charge object. If you
     *              need more than those 10, you can use this API method and the limit and starting_after
     *              parameters to page through additional refunds.]
     *
     * @param   $charge_id [ID stripe of refund]
     * @param  array  $options   [ending_before, limit, starting_after]
     *
     * @return             [A associative array with a data property that contains an array of up to
     *                            limit refunds, starting after refund starting_after.]
     */
    public function listRefunds($charge_id, $options = []) {
        return $this->getRefund($charge_id)->refunds->all($options);
    }

    /**
     * [addCustomer Creates a new customer object.]
     *
     * @param array $options [account_balance,
     *                        coupon,
     *                        description,
     *                        email,
     *                        metadata,
     *                        plan,
     *                        quantity(plans),
     *                        source (Stripe.js),
     *                        tax_percent,
     *                        trial_end]
     *
     * @return  [A customer object if the call succeeded]
     */
    public function addCustomer($options = []) {
        try {
            return \Stripe\Customer::create($options);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [getCustomer Retrieves the details of an existing customer. You need only supply the unique customer
     *              identifier that was returned upon customer creation.]
     *
     * @param   $customer_id [ID stripe of customer]
     *
     * @return               [A customer object]
     */
    public function getCustomer($customer_id) {
        return \Stripe\Customer::retrieve($customer_id);
    }

    /**
     * [updateCustomer Updates the specified customer by setting the values of the parameters passed.
     *                 Any parameters not provided will be left unchanged.]
     *
     * @param   $customer_id         [ID stripe of customer]
     *
     * @param   [array] $options     [account_balance, coupon, default_source, description, email, metadata, source]
     *
     * @return                       [A customer object]
     */
    public function updateCustomer($customer_id, $options) {
        try {
            $cu = $this->getCustomer($customer_id);
            foreach ($options as $key => $value) {
                $cu->$key = $value;
            }
            return $cu->save();
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [deleteCustomer Permanently deletes a customer. It cannot be undone. Also immediately cancels any
     *                 active subscriptions on the customer.]
     *
     * @param  $customer_id [ID stripe of customer]
     * @return [type]              [Returns an object with a deleted parameter on success. If the customer
     *                              ID does not exist, this call throws an error.]
     */
    public function deleteCustomer($customer_id) {
        $cu = $this->getCustomer($customer_id);
        return $cu->delete();
    }

    /**
     * [listCustomers Returns a list of your customers. The customers are returned sorted by creation date,
     *                with the most recently created customers appearing first.]
     *
     * @param  array  $opctions ['created' The value can be a string with an integer Unix timestamp, or it can be a dictionary with
     *                                      the following options:
     *                                      'gt' here the created field is after this timestamp.
     *                                      'gte' where the created field is after or equal to this timestamp.
     *                                      'lt' where the created field is before this timestamp.
     *                                      'lte' where the created field is before or equal to this timestamp.
     *
     *                          'ending_before' A cursor for use in pagination. ending_before is an object ID that defines your place
     *                                          in the list. For instance, if you make a list request and receive 100 objects, starting
     *                                          with obj_bar, your subsequent call can include ending_before=obj_bar in order to fetch
     *                                          the previous page of the list.
     *
     *                           'limit' A limit on the number of objects to be returned. Limit can range between 1 and 100 items.
     *
     *                           'starting_after' A cursor for use in pagination. starting_after is an object ID that defines your place
     *                                           in the list. For instance, if you make a list request and receive 100 objects, ending
     *                                           with obj_foo, your subsequent call can include starting_after=obj_foo in order to fetch
     *                                           the next page of the list.]
     *
     * @return [type]           [A associative array with a data property that contains an array of up to limit customers,
     *                             starting after customer starting_after. Each entry in the array is a separate customer
     *                             object. If no more customers are available, the resulting array will be empty.
     *                             This request should never throw an error.]
     */
    public function listCustomers($opctions = []) {
        return \Stripe\Customer::all($opctions);
    }

    /**
     * [addSubscription Creates a new subscription on an existing customer.]
     *
     * @param $customer_id [ID stripe of customer]
     *
     * @param [array] $options     [plan(REQUIRED),
     *                              coupon,
     *                              trial_end,
     *                              source(Stripe.js),
     *                              quantity,
     *                              application_fee_percent,
     *                              tax_percent,
     *                              metadata ]
     * @return   The subscription object.
     */
    public function addSubscription($customer_id, $plan_id, $metadata = FALSE, $extData = []) {
        try {
            $data = array(
                "customer" => $customer_id,
                "items" => [["plan" => $plan_id]],
                'metadata' => $metadata
            );
            if(isset($extData['trialPeriodEnd']) && !empty($extData['trialPeriodEnd'])){
                $data['trial_end'] = $extData['trialPeriodEnd'];
            }
            if(isset($extData['coupon']) && !empty($extData['coupon'])){
                $data['coupon'] = $extData['coupon'];
            }
            return \Stripe\Subscription::create($data);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [getSubscription By default, you can see the 10 most recent active subscriptions stored on a customer
     *                  directly on the customer object, but you can also retrieve details about a specific
     *                  active subscription for a customer.]
     *
     * @param   $customer_id     [ID of customer to retrieve]
     * @param   $subscription_id [ID of subscription to retrieve]
     * @return                   [The subscription object]
     */
    public function getSubscription($customer_id, $subscription_id) {
        $this->getCustomer($customer_id)->subscriptions->retrieve($subscription_id);
    }

    /**
     * [updateSubscription Updates an existing subscription on a customer to match the specified parameters.
     *                     When changing plans or quantities, we will optionally prorate the price we charge
     *                     next month to make up for any price changes. To preview how the proration will be
     *                     calculated, use the upcoming invoice endpoint.]
     *
     * @param  $customer_id     [ID of customer to retrieve]
     * @param  $subscription_id [ID of subscription to retrieve]
     *
     * @param  [array] $options [plan,
     *                           prorate,
     *                           proration_date(default is the current time),
     *                           coupon,
     *                           trial_end,
     *                           source(Stripe.js),
     *                           quantity,
     *                           application_fee_percent,
     *                           tax_percent,
     *                           metadata]
     *
     * @return [type]            [You can see a list of the customer's active subscriptions. Note that the 10 most
     *                            recent active subscriptions are always available by default on the customer object.
     *                            If you need more than those 10, you can use the limit and starting_after parameters
     *                            to page through additional subscriptions.]
     */
    public function updateSubscription($customer_id, $subscription_id, $options) {
        try {
            //$su = $this->getSubscription($customer_id, $subscription_id);
            $su = \Stripe\Subscription::retrieve($subscription_id);
            foreach ($options as $key => $value) {
                $su->$key = $value;
            }

            return $su->save();
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /**
     * [cancelSubscription Cancels a customer's subscription. If you set the at_period_end parameter to true, the subscription
     *                      will remain active until the end of the period, at which point it will be canceled and not renewed.
     *                      By default, the subscription is terminated immediately. In either case, the customer will not be
     *                      charged again for the subscription. Note, however, that any pending invoice items that you've
     *                      created will still be charged for at the end of the period unless manually deleted. If you've set
     *                      the subscription to cancel at period end, any pending prorations will also be left in place and
     *                      collected at the end of the period, but if the subscription is set to cancel immediately, pending
     *                      prorations will be removed.]
     *
     * @param  $customer_id     [ID of customer to retrieve]
     * @param  $subscription_id [ID of subscription to retrieve]
     * @param  $at_period_end [ID of subscription to retrieve]
     * @return [type]           [The canceled subscription object. Its subscription status will be set to "canceled" unless you've
     *                           set at_period_end to true when canceling, in which case the status will remain "active" but the
     *                           cancel_at_period_end attribute will change to true.]
     */
    public function cancelSubscription($customer_id, $subscription_id, $at_period_end = false) {
        return $this->getSubscription($customer_id, $subscription_id)->cancel($at_period_end);
    }

    //Plan 
    public function getPlan($id = '') {
        return \Stripe\Plan::retrieve($id);
    }

    //Plan 
    public function getInvoice($id = '') {
        return \Stripe\Invoice::retrieve($id);
    }

    public function deletePlan($id = '') {
        try {
            $plan = \Stripe\Plan::retrieve($id);
            return $plan->delete();
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    public function createPlan($options = []) {
        try {
            $options["interval"] = 'month';
            $options["currency"] = $this->config['currency'];
            return \Stripe\Plan::create($options);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    //End plan
    //Start plan
    public function deleteProduct($id = []) {
        try {
            $product = \Stripe\Product::retrieve($id);
            $product->delete();
            return \Stripe\Plan::create($options);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
    //end plan

    //Cancel subscription instant
    public function cancelSubscriptionInstant($id = []) {
        try {
            $subscription = \Stripe\Subscription::retrieve($id);
            return $subscription->cancel();
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
    // ./ Cancel subscription instant
    
    //retrieve subscription
    public function retriveSingleSubscription($id = []) {
        try {
            $subscription = \Stripe\Subscription::retrieve($id);
            return $subscription;
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
    // ./ retrieve subscription
    
    // retrieveSource 
    public function retrieveCustomerSource($cust_id = "", $bank_id = "") {
        try {
            return \Stripe\Customer::retrieveSource($cust_id, $bank_id);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
    // ./ retrieveSource 


    /*
    Create Stripe connect account
    */
    public function createStripeConnect($options = []) {
        try {
            if ( empty($options['email']) ) {
                return false;
            }
            return \Stripe\Account::create([
                'type' => 'custom',
                'country' => 'US',
                'email' => $options['email'],
                'capabilities[card_payments][requested]' => true,
                'capabilities[transfers][requested]' => true,
                /*'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],*/
            ]);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    /*
    Create Stripe connect Login link
    */
    public function createStripeConnectLoginLink($options = []) {
        try {
            if ( empty($options['accId']) ) {
                return false;
            }
            return \Stripe\Account::createLoginLink($options['accId'], []);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
    
    /*
    Create Stripe connect transfer
    */
    public function stripeConnectTransfer($amt,$connectId) {
        // Create a PaymentIntent:
        $amount = ((number_format($amt, 2, '.', ''))*100);
        try {
            // Create a Transfer to a connected account (later):
            return \Stripe\Transfer::create([
                'amount' => $amount,
                'currency' => $this->config['currency'],
                'destination' => $connectId,
            ]);
        } catch (Exception $e) {
            return $e->getJsonBody();
        } 
    }

    /*
    Create Stripe connect Onboard link
    */
    public function createStripeConnectOnBoard($options = []) {
        try {
            if ( empty($options['accId']) ) {
                return false;
            }
            return \Stripe\AccountLink::create([
                'account' => $options['accId'],
                'refresh_url' => base_url(getenv('STRIPE_OB_RETURN')),
                'return_url' => base_url(getenv('STRIPE_OB_REFRESH')),
                'type' => 'account_onboarding',
            ]);
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    public function validateWebhook($payload,$sig_header){
        $endpoint_secret = $this->config['endpoint_secret'];
        $event = null;
        try {
            return $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(Exception $e) {
            return false;
        } 
    }

    public function deleteCard($options = []) {
        try {
            return \Stripe\Customer::deleteSource(
                $options['customer_id'],
                $options['card_id'],
                []
            );
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    public function createCard($opctions) {
        try {
            return \Stripe\Customer::createSource(
                $opctions['customer_id'],
                ['source' => $opctions['source']]
            );
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    public function createBankToken($options = []) {
        try {
            $options['currency'] = $this->config['currency'];
            return \Stripe\Token::create(
                ['bank_account' => $options]
            );
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    public function createBankAccountOfConnect($connectACId="",$bankToken="",$isdefault = false) {
        try {
            $options['currency'] = $this->config['currency'];
            if($isdefault == true){
                $data = ['external_account' => $bankToken,'default_for_currency'=>true];
            }else{
                $data = ['external_account' => $bankToken];
            }
            return \Stripe\Account::createExternalAccount(
                $connectACId,
                $data
            );
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }

    public function retriveBankAccountOfConnect($connectACId="",$bankId="") {
        try {
            return \Stripe\Account::retrieveExternalAccount(
                $connectACId,
                $bankId,
                []
            );
        } catch (Exception $e) {
            return $e->getJsonBody();
        }
    }
}
