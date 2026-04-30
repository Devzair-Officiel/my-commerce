<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class GuideController extends AbstractController
{
    #[Route('/admin-guide', name: 'admin_guide')]
    public function __invoke(): Response
    {
        return $this->render('admin/guide.html.twig');
    }
}
