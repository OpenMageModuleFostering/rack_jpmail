<?php
class Rack_Jpmail_Model_Newsletter_Queue extends Mage_Newsletter_Model_Queue
{
    /**
     * Send messages to subscribers for this queue
     *
     * @param   int     $count
     * @param   array   $additionalVariables
     * @return Mage_Newsletter_Model_Queue
     */
    public function sendPerSubscriber($count=20, array $additionalVariables=array())
    {
        if($this->getQueueStatus()!=self::STATUS_SENDING
            && ($this->getQueueStatus()!=self::STATUS_NEVER && $this->getQueueStartAt())
        ) {
            return $this;
        }

        if ($this->getSubscribersCollection()->getSize() == 0) {
            $this->_finishQueue();
            return $this;
        }

        $count = Mage::getStoreConfig('newsletter/delivery/count');

        $collection = $this->getSubscribersCollection()
            ->useOnlyUnsent()
            ->showCustomerInfo()
            ->setPageSize($count)
            ->setCurPage(1)
            ->load();

        /* @var $sender Mage_Core_Model_Email_Template */
        $sender = Mage::getModel('core/email_template');
        $sender->setSenderName($this->getNewsletterSenderName())
            ->setSenderEmail($this->getNewsletterSenderEmail())
            ->setTemplateType($this->_template->getType())
            ->setTemplateSubject($this->getNewsletterSubject())
            ->setTemplateText($this->getNewsletterText())
            ->setTemplateStyles($this->getNewsletterStyles())
            ->setTemplateFilter(Mage::helper('newsletter')->getTemplateProcessor());

        foreach($collection->getItems() as $item) {
            $email = $item->getSubscriberEmail();
            $name = $item->getSubscriberFullName();

            $sender->emulateDesign($item->getStoreId());
            $successSend = $sender->send($email, $name, array('subscriber' => $item));
            $sender->revertDesign();

            if($successSend) {
                $item->received($this);
            } else {
                $problem = Mage::getModel('newsletter/problem');
                $notification = Mage::helper('newsletter')->__('Please refer to exeption.log');
                $problem->addSubscriberData($item)
                    ->addQueueData($this)
                    ->addErrorData(new Exception($notification))
                    ->save();
                $item->received($this);
            }
        }

        if(count($collection->getItems()) < $count-1 || count($collection->getItems()) == 0) {
            $this->_finishQueue();
        }
        return $this;
    }
}