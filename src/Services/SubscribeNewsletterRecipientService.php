<?php declare(strict_types=1);

namespace Fle\RegistrationNewsletterSignup\Services;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubscribeNewsletterRecipientService {

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $newsletterRecipientRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $newsletterRecipientRepository,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->customerRepository = $customerRepository;
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    private function fetchCustomerByID(string $id, Context $context) : ?CustomerEntity {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation("defaultShippingAddress");
        return $this->customerRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }

    private function fetchRecipientByEmailSalesChannel(string $email, string $salesChannel, Context $context) : ?NewsletterRecipientEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND),
            new EqualsFilter('email', $email),
            new EqualsFilter('salesChannelId', $salesChannel)
        );
        return $this
            ->newsletterRecipientRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }

    public function subscribe(CustomerEntity $customer, SalesChannelContext $salesContext) : void {

        $context = $salesContext->getContext();

        // re-fetching since the customer doesn't contain all the associated fields we need in this service
        $customer = $this->fetchCustomerByID($customer->getId(), $context);

        if ($customer->getNewsletter() && $customer->getActive()) {

            $fetchedRecipient = $this->fetchRecipientByEmailSalesChannel(
                $customer->getEmail(),
                $customer->getSalesChannelId(),
                $context
            );

            if ($fetchedRecipient) {
                // Handling the case that someone might have already signed up for the newsletter but not yet confirmed their newsletter subscription
                if (!$fetchedRecipient->getConfirmedAt()) {
                    $fetchedRecipient->setConfirmedAt(new \DateTime());
                    $fetchedRecipient->setStatus(NewsletterSubscribeRoute::STATUS_OPT_IN);
                    $this->newsletterRecipientRepository->update($fetchedRecipient->getVars(), $context);
                    $this->eventDispatcher->dispatch(new NewsletterConfirmEvent(
                        $context,
                        $fetchedRecipient,
                        $salesContext->getSalesChannel()->getId()
                    ));
                }
                return;
            }

            $recipientID = Uuid::randomHex();
            $this->newsletterRecipientRepository->create(
                [
                    [
                        'id' => $recipientID,
                        'languageId' => $context->getLanguageId(),
                        'salesChannelId' => $salesContext->getSalesChannel()->getId(),
                        'hash' => Uuid::randomHex(),
                        'email' => $customer->getEmail(),
                        'salutationId' => $customer->getSalutationId(),
                        'title' => $customer->getTitle(),
                        'firstName' => $customer->getFirstName(),
                        'lastName' => $customer->getLastName(),
                        'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
                        'zipCode' => $customer->getDefaultShippingAddress()->getZipcode(),
                        'city' => $customer->getDefaultShippingAddress()->getCity()
                    ]
                ],
                $context
            );

            $recipient = $this->newsletterRecipientRepository
                ->search(new Criteria([$recipientID]), $context)
                ->getEntities()
                ->first();

            $this->eventDispatcher->dispatch(new NewsletterConfirmEvent(
                $context,
                $recipient,
                $salesContext->getSalesChannel()->getId()
            ));

        }

    }

}
