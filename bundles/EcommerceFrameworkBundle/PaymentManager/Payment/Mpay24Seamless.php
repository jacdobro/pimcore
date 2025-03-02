<?php

namespace Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Payment;

use Mpay24\Mpay24;
use Mpay24\Mpay24Config;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractPaymentInformation;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Status;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\StatusInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\PriceInterface;
use Pimcore\Model\DataObject\Fieldcollection\Data\OrderPriceModifications;
use Pimcore\Model\DataObject\OnlineShopOrder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Exception\NotImplementedException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Templating\EngineInterface;

/**
 * Class Mpay24
 * Payment integration for Mpay24
 *
 * @see https://payment-services.ingenico.com/int/en/ogone/support/guides/integration%20guides/e-commerce/introduction
 *
 * @package Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Payment
 */
class Mpay24Seamless extends AbstractPayment
{
    /**
     * @var string[]
     */
    private $ecommerceConfig;

    /** @var EngineInterface */
    private $templatingEngine;

    private $successURL;
    private $errorURL;
    private $confirmationURL;

    public function __construct(array $options, EngineInterface $templatingEngine)
    {
        $this->templatingEngine = $templatingEngine;
        $this->configureOptions(new OptionsResolver())->resolve($options);
        $this->ecommerceConfig = $options;
        $this->confirmationURL = '';
        $this->successURL = '';
        $this->errorURL = '';
    }

    /**
     * Check options that have been passed by the main configuration
     *
     * @param OptionsResolver $resolver
     *
     * @return OptionsResolver
     */
    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        parent::configureOptions($resolver);

        $resolver->setRequired([
            'merchant_id',
            'password',
            'testSystem',
            'debugMode',
            'payment_methods',
            'partial'
        ]);

        $resolver->setAllowedTypes('payment_methods', 'array');
        $resolver->setAllowedTypes('partial', 'string');

        $notEmptyValidator = function ($value) {
            return !empty($value);
        };

        foreach ($resolver->getRequiredOptions() as $requiredProperty) {
            $resolver->setAllowedValues($requiredProperty, $notEmptyValidator);
        }

        //$resolver->setAllowedValues('testSystem', ['SHA1', 'SHA256', 'SHA512']);
        $notEmptyValidator = function ($value) {
            return !empty($value);
        };
        foreach ($resolver->getRequiredOptions() as $requiredProperty) {
            $resolver->setAllowedValues($requiredProperty, $notEmptyValidator);
        }

        return $resolver;
    }

    private function getMpay24Config(): Mpay24Config
    {
        $mpayConfig = new Mpay24Config();
        $mpayConfig->setMerchantID($this->ecommerceConfig['merchant_id']);
        $mpayConfig->setSoapPassword($this->ecommerceConfig['password']);
        $mpayConfig->useTestSystem($this->ecommerceConfig['testSystem']);
        $mpayConfig->setDebug($this->ecommerceConfig['debugMode']);
        $mpayConfig->setVerifyPeer(true);
        $mpayConfig->setLogPath(PIMCORE_LOG_DIRECTORY);
        $mpayConfig->setEnableCurlLog(true);
        $mpayConfig->setCurlLogFile('mpay24-curl.log');

        return $mpayConfig;
    }

    private function getProviderCompatibleLocale(Request $request): string
    {
        $locale = $request->getLocale();
        if (strpos($locale, '_') > 0) {
            return explode_and_trim('_', $locale)[0];
        } else {
            return $locale;
        }
    }

    /**
     * Start payment and build form, including token.
     *
     * @param PriceInterface $price
     * @param array $config
     *
     * @return FormBuilderInterface
     *
     * @throws \Exception
     */
    public function initPayment(PriceInterface $price, array $config)
    {
        /** @var Request $request */
        $request = $config['request'];
        $paymentInfo = $config['paymentInfo'];

        if (!$paymentInfo instanceof AbstractPaymentInformation) {
            throw new \Exception('PaymentInfo parameter must be of type AbstractPaymentInformation!');
        }

        $mpay24 = new \Mpay24\Mpay24($this->getMpay24Config());

        // Each line is optional so only add the lines that you need
        $tokenizerConfig = [
            'language' => $this->getProviderCompatibleLocale($request),
            'internalPaymentId' => $paymentInfo->getInternalPaymentId(),

        ];
        $tokenizer = $mpay24->token('CC', $tokenizerConfig); //always CC as cc is with iframe?
        if ($tokenizer->getStatus() == 'ERROR') {
            throw new \Exception($tokenizer->getReturnCode());
        }
        $params = [];
        $params['tokenizer'] = $tokenizer;
        $params['paymentMethods'] = $this->ecommerceConfig['payment_methods'];

        return $this->templatingEngine->render($this->ecommerceConfig['partial'], $params);
    }

    /**
     * Get payment redirect URL after payment form has been submitted with a post.
     *
     * @param $config
     *
     * @return string[] first parameter contains redirect URL, second parameter contains error message if there is any.
     * if there is an error message is it up to you to redirect to the suggested URL, which might not
     * be optimal, or to redirect back to the checkout and output the error somewhere else.
     *
     * @throws \Exception
     */
    public function getInitPaymentRedirectUrl($config): array
    {
        $request = $config['request'];
        $paymentInfo = $config['paymentInfo'];

        $this->successURL = $config['successURL'];
        $this->errorURL = $config['errorURL'];
        $this->confirmationURL = $config['confirmationURL'];

        if (empty($this->successURL)) {
            throw new \Exception('Success URL in Mpay24Seamless provider must not be empty.');
        }
        if (empty($this->errorURL)) {
            throw new \Exception('Error URL in Mpay24Seamless provider must not be empty.');
        }
        if (empty($this->confirmationURL)) {
            throw new \Exception('Confirmation URL in Mpay24Seamless provider must not be empty.');
        }

        if (!$paymentInfo instanceof AbstractPaymentInformation) {
            throw new \Exception('PaymentInfo parameter must be of type AbstractPaymentInformation!');
        }

        $mpay24 = new \Mpay24\Mpay24($this->getMpay24Config());
        $paymentType = $request->get('type');

        if ($request->isMethod('post') && isset($paymentType)) {
            $order = $config['order'];
            if (!$order instanceof AbstractOrder) {
                throw new \Exception('Order must be passed to payment provider Mpay24Seamless!');
            }

            $payment = [
                'amount' => round($order->getTotalPrice(), 2) * 100, //value in cent
                'currency' => $order->getCurrency(),
                'manualClearing' => 'false',       // Optional: set to true if you want to do a manual clearing
                'useProfile' => 'false',       // Optional: set if you want to create a profile
            ];

            $payment['token'] = $request->get('token');
            switch ($paymentType) {
                case 'CC':
                    $paymentType = 'TOKEN';
                    break;
                case 'TOKEN':
                    $payment['token'] = $request->get('token');
                    break;
            }

            // All fields are optional, but most of them are highly recommended
            //@see https://docs.mpay24.com/docs/paypal for extensions (payment - method specific)
            $additional = [
                // "customerID" => "customer123", // not set due to GDPR; required if useProfile is true
                //"customerName" => "Jon Doe", // not set due to GDPR;
                'order' =>
                    [
                        'description' => \Pimcore::getContainer()->get('translator')->trans('mpay24.general.orderDescription')
                    ],
                'successURL' => $this->successURL,
                'errorURL' => $this->errorURL,
                'confirmationURL' => $this->confirmationURL,
                'language' => $this->getProviderCompatibleLocale($request)
            ];

            //add information on item level
            //@todo switch to Mpay24SDK library, see https://github.com/mpay24/mpay24-php/pull/79#issuecomment-383528608
            $additional = $this->addOrderItemPositions($order, $paymentType, $additional);

            $result = $mpay24->payment($paymentType, $paymentInfo->getInternalPaymentId(),
                $payment, $additional);
            if ($result->getReturnCode() == 'REDIRECT') {
                return [$result->getLocation(), ''];
            } elseif ($result->hasStatusOk()) {
                if (strpos($this->successURL, '?') > 0) {
                    $forwardUrl = $this->successURL.'&TID='.$paymentInfo->getInternalPaymentId();
                } else {
                    $forwardUrl = $this->successURL.'?TID='.$paymentInfo->getInternalPaymentId();
                }

                return [$forwardUrl, ''];
            } else {
                //the standard error page works perfectly fine with the handleResponse method.
                //however, the payment action will be logged as "cancelled" and not as a user error,
                //which is misguiding. So it's better that you redirect to the checkout and show
                //the error message, and logging the error message as an object note optionally.

                $forwardUrl = $this->errorURL.'?TID='.$paymentInfo->getInternalPaymentId();

                //errText may be empty (e.g. on EXTERNAL_ERROR - invalid exceed date of CC).
                $errorText = $result->getErrText();
                if (empty($errorText)) {
                    $errorText = \Pimcore::getContainer()->get('translator')->trans('mpay24.general.payment-failed');
                }

                return [$forwardUrl, $errorText];
            }
        }
    }

    private function addOrderItemPositions(OnlineShopOrder $order, string $paymentType, array $additional): array
    {
        $checkSum = 0.0;
        $checkSumVat = 0.0;

        $pos = 1;
        $additional['order']['shoppingCart'] = [];
        foreach ($order->getItems() as $orderItem) {
            $totalPrice = round($orderItem->getTotalPrice(), 2);
            $vat = round($totalPrice - $orderItem->getTotalNetPrice(), 2);
            $checkSum += $totalPrice;
            $checkSumVat += $vat;
            $additional['order']['shoppingCart']['item-'.$pos] = [
                'productNr' => $orderItem->getProduct()->getOSProductNumber(),
                'description' => $orderItem->getProduct()->getOSName(),
                'quantity' => $orderItem->getAmount(),
                'tax' => $vat * 100,
                'amount' => $totalPrice * 100,
            ];
            $pos++;
        }

        /** @var OrderPriceModifications $modification */
        foreach ($order->getPriceModifications() as $modification) {
            $totalPrice = round($modification->getAmount(), 2);
            $vat = round($totalPrice - $modification->getNetAmount(), 2);
            $checkSum += $totalPrice;
            $checkSumVat += $vat;
            //@see: pull-request made to allow formatting of order item positions: https://github.com/mpay24/mpay24-php/pull/79/commits
            $additional['order']['shoppingCart']['item-'.$pos] = [
                'productNr' => $modification->getName(),
                'descrption' => $modification->getName(),
                'quantity' => 1,
                'tax' => $vat * 100,
                'amount' => $totalPrice * 100,
            ];
            $pos++;
        }

        if ($checkSum != $order->getTotalPrice()) {
            $difference = $order->getTotalPrice() - $checkSum;
            $differenceVat = round($order->getTotalPrice() - $order->getTotalNetPrice(), 2) - $checkSumVat;
            $additional['order']['shoppingCart']['item-'.$pos] = [
                'productNr' => 'Balance',
                'descrption' => 'Balance',
                'quantity' => 1,
                'tax' => $differenceVat * 100,
                'amount' => $difference * 100,
            ];
        }

        return $additional;
    }

    /**
     * Handles response of payment provider and creates payment status object.
     *
     * @param array $response
     *
     * @return StatusInterface
     *
     * @throws \Exception
     */
    public function handleResponse($response)
    {
        $mpay24 = new Mpay24($this->getMpay24Config());
        $params = $mpay24->paymentStatusByTID($response['TID']); //example with merchant TransaktionID

        // p_r($params);exit;
        $internalPaymentId = $response['TID'];

        $transactionParams = $params->getParams();
        $responseStatus = StatusInterface::STATUS_PENDING;
        if ($params->hasStatusOk() && $transactionParams['STATUS'] != 'ERROR') {
            $responseStatus = StatusInterface::STATUS_AUTHORIZED;
        } else {
            $responseStatus = StatusInterface::STATUS_CANCELLED;
        }

        $mpayLogData = [];
        foreach ($params->getParams() as $key => $value) {
            $mpayLogData['mpay24_'.$key] = $value;
        }

        $responseStatus = new Status(
            $internalPaymentId, //internal Payment ID
            $internalPaymentId, //paymentReference
            '',
            $responseStatus,
            $mpayLogData
        );
        $this->setAuthorizedData($params->getParams());

        return $responseStatus;
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getName()
    {
        return 'Mpay24';
    }

    /**
     * @inheritdoc
     */
    public function getAuthorizedData()
    {
        return $this->authorizedData;
    }

    /**
     * @inheritdoc
     */
    public function setAuthorizedData(array $authorizedData)
    {
        $this->authorizedData = $authorizedData;
    }

    /**
     * Executes payment
     *
     *  if price is given, recurPayment command is executed
     *  if no price is given, amount from authorized Data is used and deposit command is executed
     *
     * @param PriceInterface $price
     * @param string $reference
     *
     * @return StatusInterface
     *
     * @throws \Exception
     */
    public function executeDebit(PriceInterface $price = null, $reference = null)
    {
        throw new NotImplementedException('executeDebit is not implemented yet.');
    }

    /**
     * Executes credit
     *
     * @param PriceInterface $price
     * @param string $reference
     * @param $transactionId
     *
     * @return StatusInterface
     *
     * @throws \Exception
     */
    public function executeCredit(PriceInterface $price, $reference, $transactionId)
    {
        throw new NotImplementedException('executeCredit is not implemented yet.');
    }
}
