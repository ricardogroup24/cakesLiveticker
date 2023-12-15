<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SportsController extends AbstractController
{
    #[Route('/f1/{year}', name: 'app_f1')]
    public function f1Stats(int $year): Response
    {
        $xml = simplexml_load_string(file_get_contents("http://ergast.com/api/f1/$year/driverStandings"));
        $json = json_encode($xml);
        $data = json_decode($json, true)['StandingsTable']['StandingsList']['DriverStanding'];

        $list = [];
        foreach ($data as $line) {
            $firstName = $line['Driver']['GivenName'];
            $lastName = $line['Driver']['FamilyName'];
            $image = "./images/player/{$firstName}_$lastName.png";
            $list[] = [
                'position' => $line['@attributes']['position'],
                'image' => $image,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'number' => $line['Driver']['PermanentNumber'] ?? "-",
                'nationality' => $line['Driver']['Nationality'],
                'team' => $line['Constructor']['Name'] ?? "-",
                'wins' => $line['@attributes']['wins'] ?? 0,
                'points' => $line['@attributes']['points'] ?? 0,
            ];
        }

        $xmlConstructor = simplexml_load_string(file_get_contents("http://ergast.com/api/f1/$year/constructorStandings"));
        $jsonConstructor = json_encode($xmlConstructor);
        $dataConstructor = json_decode($jsonConstructor, true)['StandingsTable']['StandingsList']['ConstructorStanding'];

        $listConstructor = [];
        if (!empty($dataConstructor)) {
            foreach ($dataConstructor as $line) {
                if (count($listConstructor) >= 10) {
                    break;
                }
                $constructorName = $line['Constructor']['Name'];
                $image = "./images/team/$constructorName.png";
                $listConstructor[] = [
                    'position' => $line['@attributes']['position'],
                    'name' => $constructorName,
                    'image' => $image,
                    'wins' => $line['@attributes']['wins'],
                    'points' => $line['@attributes']['points'],
                ];
            }
        }

        $keys = array_keys($list[0]);

        $keysConstructor = !empty($listConstructor) ? array_keys($listConstructor[0]) : [];

        return $this->render('f1/index.html.twig', [
            'table' => $list,
            'keys' => $keys,
            'year' => $year,
            'keysConstructor' => $keysConstructor,
            'constructorData' => $listConstructor,
        ]);
    }
    #[Route('/soccer/{league}/{year}', name: 'app_soccer')]
    public function soccerTable(string $league, string $year): Response
    {
        $json = file_get_contents("https://api.openligadb.de/getbltable/$league/$year");
        $data = json_decode($json, true);
        if (empty($data)) {
            return $this->json([
                "error" => "No data available with these settings."
            ]);
        }

        $list = [];
        foreach ($data as $line) {
            $list[] = [
                'position' => count($list) + 1,
                'badge' => $line['teamIconUrl'],
                'team name' => $line['teamName'],
                'matches' => $line['matches'],
                'won' => $line['won'],
                'draw' => $line['draw'],
                'lost' => $line['lost'],
                'goals' => $line['goals'],
                'goals against' => $line['opponentGoals'],
                'goal difference' => $line['goalDiff'],
                'points' => $line['points'],
                'teamId' => $line['teamInfoId']
            ];
        }
        $keys = array_keys($list[0]);

        $user = $this->getUser();
        $userTeamId = null;
        if ($user) {
            $userTeamId = $user->getFavTeam();
        }

        $jsonScorer = file_get_contents("https://api.openligadb.de/getgoalgetters/$league/$year");
        $dataScorer = json_decode($jsonScorer, true);

        $listScorer = [];
        if (!empty($dataScorer)) {
            foreach ($dataScorer as $line) {
                if (count($listScorer) >= 10) {
                    break;
                }

                $scorerName = $line['goalGetterName'];
                $image = "./images/player/$scorerName.png";
                dump($image, file_exists($image));
                $listScorer[] = [
                    'position' => count($listScorer) + 1,
                    'image' => $image,
                    'name' => $scorerName,
                    'goal' => $line['goalCount'],
                ];
            }
        }
        $keysScorer = !empty($listScorer) ? array_keys($listScorer[0]) : [];

        return $this->render('soccer/index.html.twig', [
            'table' => $list,
            'keys' => $keys,
            'league' => $league,
            'year' => $year,
            'userTeamId' => $userTeamId,
            'keysScorer' => $keysScorer,
            'scorerData' => $listScorer,
        ]);
    }
    #[Route("/", name: 'app_home')]
    public function homePage(Request $request)
    {
        $formValues = $request->get('form');
        $league = $formValues && array_key_exists('league', $formValues) ? $formValues['league'] : null;
        $season = $formValues && array_key_exists('season', $formValues) && !empty($formValues['league']) ? $formValues['season'] : null;

        $form = $this->createFormBuilder(['league' => $league, 'season' => $season])
            ->add('league', ChoiceType::class, [
                'choices'  => [
                    'Bitte wählen Sie eine Liga' => '',
                    '1. Bundesliga' => 'bl1',
                    '2. Bundesliga' => 'bl2',
                    '3. Liga' => 'bl3',
                    'Österreichische Bundesliga' => 'öbl1',
                    'Regionalliga Nordost' => 'rlno',
                    'Formel 1' => 'f1',
                ],
                'attr' => ['onchange' => 'this.form.submit()'],
            ])
            ->add('season', ChoiceType::class, [
                'choices' => $this->getSeasons($league),
                'disabled' => empty($league),
            ])
            ->add('save', SubmitType::class, ['label' => 'Submit', 'attr' => ['class' => 'submit-button']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !empty($form->getData()['league']) && !empty($form->getData()['season'])) {
            $data = $form->getData();
            if ($data['league'] == 'f1') {
                return $this->redirectToRoute('app_f1', ['year' => $data['season']]);
            } else {
                return $this->redirectToRoute('app_soccer', ['league' => $data['league'], 'year' => $data['season']]);
            }
        }
        return $this->render('home/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function getSeasons($league)
    {
        $f1seasons = ['' => ''];
        for ($year = 2023; $year >= 1950; $year--) {
            $f1seasons[strval($year)] = $year;
        }
        switch ($league) {
            case 'bl1':
                return [
                    '' => '',
                    '2023' => 2023,
                    '2022' => 2022,
                    '2021' => 2021,
                    '2020' => 2020,
                    '2019' => 2019,
                    '2018' => 2018,
                    '2017' => 2017,
                    '2016' => 2016,
                    '2015' => 2015,
                    '2014' => 2014,
                    '2013' => 2013,
                    '2012' => 2012,
                    '2011' => 2011,
                    '2010' => 2010,
                    '2009' => 2009,
                    '2008' => 2008,
                ];
            case 'bl2':
                return [
                    '' => '',
                    '2023' => 2023,
                    '2022' => 2022,
                    '2021' => 2021,
                    '2020' => 2020,
                    '2019' => 2019,
                    '2018' => 2018,
                    '2017' => 2017,
                    '2016' => 2016,
                    '2015' => 2015,
                    '2014' => 2014,
                    '2013' => 2013,
                    '2012' => 2012,
                    '2011' => 2011,
                ];
            case 'bl3':
                return [
                    '' => '',
                    '2023' => 2023,
                    '2022' => 2022,
                    '2021' => 2021,
                    '2020' => 2020,
                    '2019' => 2019,
                    '2018' => 2018,
                    '2017' => 2017,
                    '2016' => 2016,
                    '2015' => 2015,
                    '2014' => 2014,
                    '2013' => 2013,
                    '2012' => 2012,
                ];
            case 'rlno':
            case 'öbl1':
                return [
                    '' => '',
                    '2023' => 2023,
                ];
            case 'f1':
                return $f1seasons;
            // Fügen Sie hier weitere Fälle hinzu...
            default:
                return [];
        }
    }
    #[Route("/favteamform/", name: 'app_favteamform')]
    public function favouriteTeamForm(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('league', ChoiceType::class, [
                'choices'  => [
                    '1. Bundesliga' => 'bl1',
                    '2. Bundesliga' => 'bl2',
                    '3. Liga' => 'bl3',
                    'Österreichische Bundesliga' => 'öbl1',
                    'Regionalliga Nordost' => 'rlno',
                    'Formula 1' => 'f1'
                ],
            ])
            ->add('season', ChoiceType::class, [
                'choices' => [
                    '2023' => 2023,
                    '2022' => 2022,
                    '2021' => 2021,
                    '2020' => 2020,
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Submit', 'attr' => ['class' => 'submit-button']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            return $this->redirectToRoute('app_favteam', ['league' => $data['league'], 'year' => $data['season']]);
        }

        return $this->render('favteamform/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route("/favteam/{league}/{year}", name: 'app_favteam')]
    public function favouriteTeamSoccer(string $league, int $year)
    {
      /*  if ($league = "f1"){
            $xml = simplexml_load_string(file_get_contents("http://ergast.com/api/f1/$year/driverStandings"));
            $json = json_encode($xml);
            $data = json_decode($json, true)['StandingsTable']['StandingsList']['DriverStanding'];

            $list = [];
            foreach ($data as $line) {
                $list[] = [
                    'Name' => $line['Constructor']['Name'] ?? "-",
                    'TeamID' => $line['Constructor']['constructorId'] ?? "-",
                    'Badge' => $line['Constructor']['Nationality'] ?? "-",
                ];
            }

            $keys = array_keys($list[0]);

            return $this->render('favteam/index.html.twig', [
                'table' => $list,
                'keys' => $keys,
                'year' => $year
            ]);
        }
        else{ */
        $json = file_get_contents("https://api.openligadb.de/getavailableteams/$league/$year");
        $data = json_decode($json, true);

        if (empty($data)) {
            return $this->json([
                "error" => "No data available with these settings."
            ]);
        }

        $list = [];
        foreach ($data as $line) {
            $list[] = [
                'TeamID' => $line['teamId'],
                'Name' => $line['shortName'],
                'Badge' => $line['teamIconUrl']
            ];
        }
        $keys = array_keys($list[0]);
        return $this->render('favteam/index.html.twig', [
            'table' => $list,
            'keys' => $keys,
            'league' => $league,
            'year' => $year,
        ]);
        }

}