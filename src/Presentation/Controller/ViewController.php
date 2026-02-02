<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ViewController extends AbstractController
{
    #[Route('/login', name: 'login_page', methods: ['GET'])]
    public function loginPage(): Response
    {
        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'register_page', methods: ['GET'])]
    public function registerPage(): Response
    {
        return $this->render('auth/register.html.twig');
    }
}