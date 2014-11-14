<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\FormBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;

/**
 * Class LeadSubscriber
 *
 * @package Mautic\FormBundle\EventListener
 */
class LeadSubscriber extends CommonSubscriber
{

    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            LeadEvents::TIMELINE_ON_GENERATE => array('onTimelineGenerate', 0)
        );
    }

    /**
     * Compile events for the lead timeline
     *
     * @param LeadTimelineEvent $event
     */
    public function onTimelineGenerate(LeadTimelineEvent $event)
    {
        // Set available event types
        $eventTypeKey = 'form.submitted';
        $eventTypeName = $this->translator->trans('mautic.form.event.submitted');
        $event->addEventType($eventTypeKey, $eventTypeName);

        // Decide if those events are filtered
        $filter = $event->getEventFilter();
        $loadAllEvents = !isset($filter[0]);
        $eventFilterExists = in_array($eventTypeKey, $filter);

        if (!$loadAllEvents && !$eventFilterExists) {
            return;
        }

        $lead    = $event->getLead();
        $options = array('ipIds' => array(), 'filters' => $filter);

        /** @var \Mautic\CoreBundle\Entity\IpAddress $ip */
        foreach ($lead->getIpAddresses() as $ip) {
            $options['ipIds'][] = $ip->getId();
        }

        /** @var \Mautic\FormBundle\Entity\SubmissionRepository $submissionRepository */
        $submissionRepository = $this->factory->getEntityManager()->getRepository('MauticFormBundle:Submission');

        $rows = $submissionRepository->getSubmissions($options);

        $pageModel = $this->factory->getModel('page.page');
        $formModel = $this->factory->getModel('form.form');

        // Add the submissions to the event array
        foreach ($rows as $row) {
            $event->addEvent(array(
                'event'     => $eventTypeKey,
                'eventLabel' => $eventTypeName,
                'timestamp' => new \DateTime($row['dateSubmitted']),
                'extra'     => array(
                    'form'  => $formModel->getEntity($row['form_id']),
                    'page'  => $pageModel->getEntity($row['page_id'])
                ),
                'contentTemplate' => 'MauticFormBundle:Timeline:index.html.php'
            ));
        }
    }
}