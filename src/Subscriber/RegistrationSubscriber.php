<?php declare(strict_types=1);

namespace Fle\RegistrationNewsletterSignup\Subscriber;

use Fle\RegistrationNewsletterSignup\Services\SubscribeNewsletterRecipientService;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegistrationSubscriber implements EventSubscriberInterface {

    /**
     * @var SubscribeNewsletterRecipientService
     */
    private $subscribeNewsletterRecipientService;

    public function __construct(
        SubscribeNewsletterRecipientService $subscribeNewsletterRecipientService
    )
    {
        $this->subscribeNewsletterRecipientService = $subscribeNewsletterRecipientService;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomerRegisterEvent::class => 'onCustomerRegister',
            GuestCustomerRegisterEvent::class => 'onGuestCustomerRegistration',
            CustomerEvents::MAPPING_REGISTER_CUSTOMER => 'onMappingRegisterCustomerData',
        ];
    }

    public function onMappingRegisterCustomerData(DataMappingEvent $event) {
        $customerRequestData = $event->getInput();
        $newOutput = $event->getOutput();
        $newOutput["newsletter"] = $customerRequestData->get("newsletter") === "on";
        $event->setOutput($newOutput);
    }

    public function onCustomerRegister(CustomerRegisterEvent $event) {
        $this->subscribeNewsletterRecipientService->subscribe(
            $event->getCustomer(),
            $event->getSalesChannelContext()
        );
    }

    public function onGuestCustomerRegistration(GuestCustomerRegisterEvent $event) {
        $this->subscribeNewsletterRecipientService->subscribe(
            $event->getCustomer(),
            $event->getSalesChannelContext()
        );
    }

}
