<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Repository\ParticipantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NotificationEventSubscriber implements EventSubscriberInterface
{
    private $tokenStorage;
    private $em;
    private $request;
    private $participantRepo;
    private $userRepo;
    private $user;


    //On injecte nos dépendances
    public function __construct(TokenStorageInterface $tokenStorage,
                                EntityManagerInterface $em,
                                RequestStack $request,
                                ParticipantRepository $participantRepo,
                                UserRepository $userRepo)

    {
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
        $this->request = $request;
        $this->participantRepo = $participantRepo;
        $this->userRepo = $userRepo;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function onControllerEvent(ControllerEvent $event): void
    {
        $routeName = $event->getRequest()->attributes->get('_route');

        //Si il y'a du changement dans annonce de l'événement: on veut notifier tous les participants
        if ($routeName == "app_event_delete_annonce" ||
            $routeName == "event_annonce_add" ||
            $routeName == "event_annonce_update_success"
        ){


            //On récupére le nom de l'événement présent dans la requéte
            $name = $event->getRequest()->attributes->get('_route_params')['name'];

            //On cherche la liste des participants dont l'événement des eventName
            $participants = $this->participantRepo->findBy(['eventName' => $name]);
            //Pour chaque participant on crée une notification, on renseigne les données de la table notification puis on enregistre
            foreach ($participants as $participant){

                $notification = new Notification;
                $notification->setCategorie('event');
                $notification->setName($name);

                $user = $this->userRepo->findOneBy(['username' => $participant->getParticipantUsername()]);
                $notification->setUser($user);

                $this->em->persist($notification);
            }
            $this->em->flush();

        }

        if ($routeName == "app_event_news"){
            $name = $event->getRequest()->attributes->get('_route_params')['name'];
            $notifications = $this->user->getNotifications()->toArray();
            foreach ( $notifications as $notification ){
                if($notification->getName() == $name){
                   $user = $this->user->removeNotification($notification);
                }
            }
            $this->em->persist($user);
            $this->em->flush();

        }

    }

    public static function getSubscribedEvents(): array
    {
        return [
            ControllerEvent::class => 'onControllerEvent',
        ];
    }
}
