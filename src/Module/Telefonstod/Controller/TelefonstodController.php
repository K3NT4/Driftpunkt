<?php

declare(strict_types=1);

namespace App\Module\Telefonstod\Controller;

use App\Module\Telefonstod\Service\TelefonstodDashboardBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TelefonstodController extends AbstractController
{
    public function __construct(private readonly TelefonstodDashboardBuilder $dashboardBuilder)
    {
    }

    #[Route('/portal/admin/telefonstod', name: 'app_portal_admin_telefonstod', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): Response
    {
        $searchQuery = trim((string) $request->query->get('q'));

        return $this->render('portal/admin_telefonstod.html.twig', [
            'title' => 'Telefonstod',
            'summary' => 'Samlad yta for telefoniarenden, kundkort och Wx3-relaterad uppfoljning.',
            'telefonstod' => $this->dashboardBuilder->build($searchQuery),
            'searchQuery' => $searchQuery,
        ]);
    }
}
