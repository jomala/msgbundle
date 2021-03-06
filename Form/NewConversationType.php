<?php

namespace Calitarus\MessagingBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Form\InteractionType;


class NewConversationType extends AbstractType {

	private $recipients;
	private $distance;
	private $character;
	private $settlement;

	public function __construct($recipients, $distance, $character, $settlement=null) {
		$this->recipients = $recipients;
		$this->distance = $distance;
		$this->character = $character;
		$this->settlement = $settlement;
	}

	public function getName() {
		return 'new_conversation';
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'new_conversation_134',
			'translation_domain' => 'MsgBundle'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('topic', 'text', array(
			'required' => true,
			'label' => 'conversation.topic.label',
			'attr' => array('size'=>40, 'maxlength'=>80)
		));

		// TODO: restrict to topics I have access (metadata with write access) to
/* disabled until I fix that
		$builder->add('parent', 'entity', array(
			'required' => false,
			'empty_value' => 'conversation.parent.empty',
			'label' => 'conversation.parent.label',
			'class' => 'MsgBundle:Conversation',
			'property' => 'topic'
		));
*/

		$builder->add('content', 'textarea', array(
			'label' => 'message.content.label',
			'trim' => true,
			'required' => true
		));

		$me = $this->character;
		$maxdistance = $this->distance;

		// Might & Fealty: contact nearby characters
		$builder->add('nearby', 'entity', array(
			'required' => false,
			'multiple'=>true,
			'expanded'=>true,
			'label'=>'conversation.nearby.label',
			'class'=>'BM2SiteBundle:Character', 'property'=>'name', 'query_builder'=>function(EntityRepository $er) use ($me, $maxdistance) {
				$qb = $er->createQueryBuilder('c');
				$qb->from('BM2\SiteBundle\Entity\Character', 'me');
				$qb->where('c.alive = true');
				$qb->andWhere('me = :me')->andWhere('c != me')->setParameter('me', $me);
				if ($maxdistance) {
					$qb->andWhere('ST_Distance(me.location, c.location) < :maxdistance')->setParameter('maxdistance', $maxdistance);					
				}
				if ($inside = $me->getInsideSettlement()) {
					$qb->andWhere('c.inside_settlement = :inside')->setParameter('inside', $inside);
				} else {
					$qb->andWhere('c.inside_settlement IS NULL');
				}
				return $qb;
		}));

		if ($this->settlement && $this->settlement->getOwner() && $this->settlement->getOwner() != $me) {
			$owner = $this->settlement->getOwner();
			$builder->add('owner', 'entity', array(
				'required' => false,
				'multiple'=>true,
				'expanded'=>true,
				'label'=>'conversation.owner.label',
				'class'=>'BM2SiteBundle:Character', 'property'=>'name', 'query_builder'=>function(EntityRepository $er) use ($owner) {
					$qb = $er->createQueryBuilder('c');
					$qb->where('c.alive = true');
					$qb->andWhere('c = :owner')->setParameter('owner', $owner);
					return $qb;
			}));			
		}

		if ($this->recipients) {
			$recipients = $this->recipients;
			$builder->add('contacts', 'entity', array(
				'required' => false,
				'multiple'=>true,
				'expanded'=>true,
				'label' => 'conversation.recipients.label',
				'empty_value' => 'conversation.recipients.empty',
				'label' => 'conversation.recipients.label',
				'class' => 'MsgBundle:User',
				'property' => 'name',
				'query_builder'=>function(EntityRepository $er) use ($recipients) {
					$qb = $er->createQueryBuilder('u');
					$qb->where('u IN (:recipients)');
					$qb->setParameter('recipients', $recipients);
					return $qb;
				},
			));
		}


		$builder->add('submit', 'submit', array('label'=>'conversation.create'));
	}


}
