<?php

namespace App\Controller;

use App\Entity\Reservation;
use Hateoas\Representation\CollectionRepresentation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
// use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationException;


use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Symfony\Component\Uid\UuidV4;

class ReservationController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // This route is for getting a list of all reservations
    #[Route('reservation', name: 'reservation_listing', methods: ['GET'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Returns the list of reservations",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=reservation::class, groups={"reservation"}))
     *     )
     * )
     * @OA\Tag(name="reservation")
     */
    public function list(SerializerInterface $serializer, Request $request): Response
    {
        $reservations = $this->entityManager->getRepository(Reservation::class)->findAllReservations(
            $request->query->get('page', 1),
            $request->query->get('pageSize', 10)
        );

        return $this->apiResponse(
            $serializer,
            $reservations,
            $request->getAcceptableContentTypes(),
            '200',
            ['reservation']
        );
    }

    // This route is for getting a specific reservation by ID
    #[Route('reservation/{uid}', name: 'get_reservation', methods: ['GET'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Returns a reservation by ID",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Reservation::class, groups={"reservation"}))
     *     )
     * )
     * @OA\Tag(name="Reservation")
     */
    public function getReservation(string $uid, SerializerInterface $serializer, Request $request): Response
    {
        $reservation = $this->entityManager->getRepository(Reservation::class)->find($uid);

        if (!$reservation) {
            return $this->json(['message' => 'Cinéma non trouvé'], 404);
        }

        return $this->apiResponse($serializer, ['reservation' => $reservation], $request->getAcceptableContentTypes()[0], 200,
            ['reservation']);
    }

    // This route is for getting a list of all reservations
    #[Route('reservation', name: 'create_reservation', methods: ['POST'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Add a reservation",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=reservation::class, groups={"reservation"}))
     *     )
     * )
     * @OA\Tag(name="reservation")
     */
    public function add(SerializerInterface $serializer, Request $request, ValidatorInterface $validator): Response
    {

        $reservation = $serializer->deserialize($request->getContent(), Reservation::class, "json");

        $errors = $validator->validate($reservation);
        if ($errors->count() > 0) {
            return $this->apiResponse(
                $serializer,
                [
                    "status" => 422,
                    "message" => "Le contenu de l'objet reservation dans le body est invalide"
                ],
                $request->getAcceptableContentTypes(),
                '422',
                ['reservation']
            );
        }

        $this->entityManager->persist($reservation);

        $this->entityManager->flush();

        return $this->apiResponse(
            $serializer,
            [
                "reservation" => $reservation,
                "message" => "Le cinéma est créé avec succès"
            ],
            $request->getAcceptableContentTypes(),
            '201',
            ['reservation']
        );
    }

    // This route is for getting a list of all reservations
    #[Route('reservation/{uid}', name: 'edit_reservation', methods: ['PUT'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Edit a reservation",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=reservation::class, groups={"reservation"}))
     *     )
     * )
     * @OA\Tag(name="reservation")
     */
    public function edit(UuidV4 $uid, SerializerInterface $serializer, Request $request): Response
    {
        $existingReservation = $this->entityManager->getRepository(Reservation::class)->findOneByUid($uid);

        $reservation = $serializer->deserialize($request->getContent(), Reservation::class, "json", [AbstractNormalizer::OBJECT_TO_POPULATE => $existingReservation]);

        $user_data = json_decode($request->getContent(), true);

        // Check required fields
        $requiredFields = ['name'];
        foreach ($requiredFields as $field) {
            if (!isset($user_data[$field])) {
                return $this->json(['message' => "Le contenu de l'objet reservation dans le body est invalide, le champ '$field' est manquant"], 422);
            }
        }

        $this->entityManager->persist($reservation);

        $this->entityManager->flush();

        return $this->apiResponse(
            $serializer,
            [
                'message' => "Le cinéma est mis à jour avec succès"
            ],
            $request->getAcceptableContentTypes(),
            200,
            ['reservation']
        );
    }


    // This route is for deleting an existing reservation by ID
    #[Route('reservation/{uid}', name: 'delete_reservation', methods: ['DELETE'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Le reservation a été supprimé avec succès",
     *     @OA\JsonContent(ref=@Model(type=reservation::class, groups={"reservation"}))
     * )
     * @OA\Response(
     *     response=404,
     *     description="Le reservation est inconnu"
     * )
     * @OA\Tag(name="reservation")
     */
    public function delete(string $uid, SerializerInterface $serializer, Request $request): Response
    {
        $reservation = $this->entityManager->getRepository(Reservation::class)->find($uid);

        if ($reservation) {
            $this->entityManager->remove($reservation);
            $this->entityManager->flush();
            $message = 'Le reservation a été supprimé avec succès';
            $statusCode = 200;
        } else {
            $message = 'Le reservation est inconnu';
            $statusCode = 404;
        }

        return $this->apiResponse($serializer, ['message' => $message], $request->getAcceptableContentTypes()[0], $statusCode,
            ['reservation']);
    }

    // this function is to return a response in JSON or XML format
    public function apiResponse(SerializerInterface $serializer, $data, $format, $statusCode, $groups = null): Response
    {
        $xmlMime = 'application/xml';
        $context = SerializationContext::create()->setGroups($groups);
        $contentType = $format == $xmlMime ? $xmlMime : 'application/json';
        $format = $contentType == $xmlMime ? 'xml' : 'json';

        $responseContent = $serializer->serialize($data, $format, $context);
        $response = new Response($responseContent, $statusCode);
        $response->headers->set('Content-Type', $contentType);

        return $response;
    }
}