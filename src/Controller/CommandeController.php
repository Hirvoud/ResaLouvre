<?php

namespace App\Controller;

use App\Exception\CommandeNotFoundException;
use App\Form\CommandeBilletsType;
use App\Form\CommandeType;
use App\Manager\CommandeManager;
use App\Service\Mailing;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class CommandeController extends AbstractController
{
    /**
     * @Route("/", name="home")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function home()
    {
        return $this->render("commande/index.html.twig");
    }

    /**
     * @Route("/commande", name="order")
     * @param Request $request
     * @param CommandeManager $commandeManager
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function index(Request $request, CommandeManager $commandeManager)
    {

        $commande = $commandeManager->initCommande();

        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $commandeManager->creationBillets($commande);

            return $this->redirectToRoute("select");
        }

        return $this->render("commande/order.html.twig", array(
            "orderForm" => $form->createView(),
        ));
    }

    /**
     * @Route("/selection", name="select")
     * @param Request $request
     * @param CommandeManager $commandeManager
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws CommandeNotFoundException
     */
    public function select(Request $request, CommandeManager $commandeManager)
    {
        $commande = $commandeManager->getCurrentCommande();

        if($commandeManager->checkWarningTickets($commande)) {
            $this->addFlash(
                "warning",
                "Attention, il reste moins de 25 billets disponibles pour ce jour."
            );
        }

        $orderForm = $this->createForm(CommandeBilletsType::class, $commande);
        $orderForm->handleRequest($request);

        if ($orderForm->isSubmitted() && $orderForm->isValid()) {
            $commandeManager->computePrice($commande);
            return $this->redirectToRoute("confirm");
        }

        return $this->render("commande/select.html.twig", array(
            "ticketForm" => $orderForm->createView(),
        ));
    }

    /**
     * @Route("/confirmation", name="confirm")
     * @param CommandeManager $commandeManager
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \App\Exception\CommandeNotFoundException
     */
    public function confirm(CommandeManager $commandeManager)
    {
        $commande = $commandeManager->getCurrentCommande();

        $billets = $commande->getBillets();

        return $this->render("commande/confirm.html.twig", array(
            "commande" => $commande,
            "billets" => $billets
        ));
    }

    /**
     * @Route("/testmail", name="testmail")
     * @param CommandeManager $commandeManager
     * @param Mailing $mailing
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws CommandeNotFoundException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function testMail(CommandeManager $commandeManager, Mailing $mailing)
    {
        $commande = $commandeManager->getCurrentCommande();

        $mailing->SendMail($commande);

        return $this->redirectToRoute("confirm");
    } //TODO Supprimer cette fonction

    /**
     * @Route("/succes", name="success")
     * @param CommandeManager $commandeManager
     * @param ObjectManager $manager
     * @param Mailing $mailing
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @throws CommandeNotFoundException
     */
    public function success(CommandeManager $commandeManager, ObjectManager $manager, Mailing $mailing)
    {
        $commande = $commandeManager->getCurrentCommande();

        $manager->persist($commande);
        $manager->flush();

        $mailing->SendMail($commande);

        dump($commande);

        return $this->render("commande/success.html.twig", array(
            "email" => $commande->getEmail()
        ));
    }

    /**
     * @Route("/checkout", name="checkout", methods={"POST"})
     * @param CommandeManager $commandeManager
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws CommandeNotFoundException
     */
    public function checkout(CommandeManager $commandeManager)
    {
        $commande = $commandeManager->getCurrentCommande();

        $commandeManager->generateOrderId($commande);

        $checkout = $commandeManager->payment($commande);

        if ($checkout == true) {
            return $this->redirectToRoute("success");
        } else {
            $this->addFlash(
                "error",
                "Une erreur s'est produite durant le paiement. Veuillez réessayer s'il vous plaît."
            );
            return $this->redirectToRoute("confirm");
        }
    }

    /**
     * @Route("/erreur", name="error")
     */
    public function error()
    {
        return $this->render("commande/error.html.twig");
    }

    /**
     * @Route("/cancel", name="cancel")
     * @param CommandeManager $commandeManager
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cancel(CommandeManager $commandeManager)
    {
        $commandeManager->clearSession();

        return $this->redirectToRoute("home");
    }

    /**
     * @Route("/mentions-legales", name="mentions")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function mentions()
    {
        return $this->render("commande/mentions.html.twig");
    }

    /**
     * @Route("/contact", name="contact")
     * @param Request $request
     * @param Mailing $mailing
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function contact(Request $request, Mailing $mailing)
    {
        if($request->isMethod('POST')) {
            $mailing->SendContactMail($request);

            $this->addFlash(
                "contact-mail",
                "Votre message a bien été envoyé, vous pouvez retourner à l'accueil du site"
            );
        }

        return $this->render("commande/contact.html.twig");
    }

    /**
     * @Route("/en-fr", name="translate")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function translate()
    {
        setlocale(LC_ALL, "en");

        return $this->redirectToRoute("home");
    }
}
