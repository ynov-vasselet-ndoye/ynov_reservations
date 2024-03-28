<?php

namespace App\Controller;

use App\Entity\Cinema;
use App\Entity\Room;
use App\Entity\Sceance;
use Hateoas\Representation\CollectionRepresentation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface as Nserializer;
use Symfony\Component\HttpFoundation\Response;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationException;


use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Symfony\Component\Uid\UuidV4;

class SceanceController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // This route is for getting a list of all sceances
    #[Route('room/{roomUid}/sceances', name: 'sceance_listing', methods: ['GET'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Returns the list of sceances",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=sceance::class, groups={"sceance"}))
     *     )
     * )
     * @OA\Tag(name="sceance")
     */
    public function list(UuidV4 $roomUid, SerializerInterface $serializer, Request $request): Response
    {
        $sceances = $this->entityManager->getRepository(Room::class)->find($roomUid)->getSceances();

        return $this->apiResponse(
            $serializer,
            $sceances,
            $request->getAcceptableContentTypes(),
            '200',
            ['sceance']
        );
    }

    // This route is for getting a specific sceance by ID
    #[Route('room/{roomUid}/sceances/{uid}', name: 'get_sceance', methods: ['GET'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Returns a sceance by ID",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Sceance::class, groups={"sceance"}))
     *     )
     * )
     * @OA\Tag(name="Sceance")
     */
    public function getSceance(UuidV4 $roomUid, Sceance $sceance, SerializerInterface $serializer, Request $request): Response
    {
        $room = $this->entityManager->getRepository(Room::class)->find($roomUid);

        if (!$room) {
            return $this->json(['message' => 'Cinéma non trouvé'], 404);
        }

        return $this->apiResponse($serializer, ['sceance' => $sceance], $request->getAcceptableContentTypes()[0], 200,
            ['sceance']);
    }

    // This route is for getting a list of all sceances
    #[Route('/cinema/{cinemaUid}/rooms/{roomUid}/sceances', name: 'create_sceance', methods: ['POST'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Add a sceance",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=sceance::class, groups={"sceance"}))
     *     )
     * )
     * @OA\Tag(name="sceance")
     */
    public function add(UuidV4 $cinemaUid, UuidV4 $roomUid, SerializerInterface $serializer, Request $request, ValidatorInterface $validator): Response
    {
        $cinema = $this->entityManager->getRepository(Cinema::class)->find($cinemaUid);
        $room = $cinema->getRoom($roomUid);
        
        $sceance = $serializer->deserialize($request->getContent(), Sceance::class, "json");


        $errors = $validator->validate($sceance);
        if ($errors->count() > 0) {
            return $this->apiResponse(
                $serializer,
                [
                    "status" => 422,
                    "message" => "Objet non valide: " . $errors[0]->getMessage()
                ],
                $request->getAcceptableContentTypes(),
                '422',
                ['sceance']
            );
        }

        $room->addSceance($sceance);

        $this->entityManager->persist($sceance);
        $this->entityManager->persist($room);

        $this->entityManager->flush();

        return $this->apiResponse(
            $serializer,
            [
                "sceance" => $sceance,
                "message" => "Le cinéma est créé avec succès"
            ],
            $request->getAcceptableContentTypes(),
            '201',
            ['sceance']
        );
    }

    // This route is for getting a list of all sceances
    #[Route('room/{roomUid}/sceances/{uid}', name: 'edit_sceance', methods: ['PUT'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Edit a sceance",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=sceance::class, groups={"sceance"}))
     *     )
     * )
     * @OA\Tag(name="sceance")
     */
    public function edit(UuidV4 $roomUid, UuidV4 $uid, SerializerInterface $serializer, Nserializer $nserializer, Request $request, ValidatorInterface $validator): Response
    {
        $room = $this->entityManager->getRepository(Room::class)->find($roomUid);

        if (!$room) {
            return $this->apiResponse($serializer, ['message' => "Cinéma non trouvé"], $request->getAcceptableContentTypes()[0], 404,
                ['sceance']);
        }

        $sceance = $room->getSceance($uid);

        if(!$sceance) {
            return $this->apiResponse($serializer, ['message' => "Salle non trouvé"], $request->getAcceptableContentTypes()[0], 404,
                ['sceance']);
        }

        $nserializer->deserialize($request->getContent(), Sceance::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $sceance
        ]);

        $errors = $validator->validate($sceance);
        if ($errors->count() > 0) {
            return $this->apiResponse(
                $serializer,
                [
                    "status" => 422,
                    "message" => "Objet non valide: " . $errors[0]->getMessage()
                ],
                $request->getAcceptableContentTypes(),
                '422',
                ['sceance']
            );
        }

        $this->entityManager->persist($sceance);

        $this->entityManager->flush();

        return $this->apiResponse(
            $serializer,
            [
                'message' => "La salle a été mise à jour avec succès"
            ],
            $request->getAcceptableContentTypes(),
            200,
            ['sceance']
        );
    }


    // This route is for deleting an existing sceance by ID
    #[Route('/cinema{cinemaUid}/{roomUid}/sceances/{uid}', name: 'delete_sceance', methods: ['DELETE'])]
    /**
     * @OA\Response(
     *     response=200,
     *     description="Le sceance a été supprimé avec succès",
     *     @OA\JsonContent(ref=@Model(type=sceance::class, groups={"sceance"}))
     * )
     * @OA\Response(
     *     response=404,
     *     description="Le sceance est inconnu"
     * )
     * @OA\Tag(name="sceance")
     */
    public function delete(UuidV4 $roomUid, UuidV4 $uid, SerializerInterface $serializer, Request $request): Response
    {

        $room = $this->entityManager->getRepository(Room::class)->find($roomUid);

        if (!$room) {
            return $this->apiResponse($serializer, ['message' => "Cinéma non trouvé"], $request->getAcceptableContentTypes()[0], 404,
                ['sceance']);
        }

        $sceance = $room->getSceance($uid);

        if ($sceance) {
            $room->removeSceance($sceance);
            $this->entityManager->remove($sceance);
            $this->entityManager->flush();
            $message = 'La salle a été supprimée avec succès';
            $statusCode = 200;
        } else {
            $message = 'La salle est inconnue';
            $statusCode = 404;
        }

        return $this->apiResponse($serializer, ['message' => $message], $request->getAcceptableContentTypes()[0], $statusCode,
            ['sceance']);
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
