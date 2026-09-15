<?php

namespace App\Controller;

use App\Entity\Note;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class NoteController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): JsonResponse
    {
        return new JsonResponse([
            'app' => 'kiloupabocou',
            'status' => 'ok',
        ]);
    }

    #[Route('/notes', name: 'notes_index', methods: ['GET'])]
    public function index(NoteRepository $notes): JsonResponse
    {
        $data = array_map(
            fn (Note $note) => [
                'id' => $note->getId(),
                'title' => $note->getTitle(),
                'content' => $note->getContent(),
                'createdAt' => $note->getCreatedAt()?->format(DATE_ATOM),
            ],
            $notes->findAll()
        );

        return new JsonResponse($data);
    }

    #[Route('/notes', name: 'notes_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        if (empty($payload['title']) || empty($payload['content'])) {
            return new JsonResponse(['error' => 'title and content are required'], 422);
        }

        $note = new Note();
        $note->setTitle($payload['title']);
        $note->setContent($payload['content']);

        $em->persist($note);
        $em->flush();

        return new JsonResponse(['id' => $note->getId()], 201);
    }
}
