<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class SaveFavTeamController extends AbstractController
{
    #[Route("/saveFavTeam", name: "save_fav_team")]
    public function save(Request $request, EntityManagerInterface $em): Response
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['teamId'])) {
                return new Response('teamId fehlt', Response::HTTP_BAD_REQUEST);
            }

            // Ersetzen Sie dies durch Ihre Logik, um den Benutzer zu authentifizieren
            $user = $this->getUser();

            if (!$user) {
                return new Response('Benutzer nicht gefunden', Response::HTTP_UNAUTHORIZED);
            }

            // Setzen Sie FavTeam für den Benutzer
            $user->setFavTeam($data['teamId']);
            $em->persist($user);
            $em->flush();
        } catch (\Exception $e) {
            return new Response("Speichern fehlgeschlagen, weil: " . $e->getMessage());
        }


        return new Response("Lieblingsteam erfolgreich gespeichert");
    }
}