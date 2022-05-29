<?php

namespace App\Command;

use App\Entity\Film;
use App\Entity\Actor;
use App\Entity\Director;
use Doctrine\DBAL\Connection;
use App\Repository\FilmRepository;
use App\Repository\ActorRepository;
use App\Repository\DirectorRepository;
use Symfony\Component\Filesystem\Path;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;


#[AsCommand(
    name: 'app:import:csv',
    description: 'Load csv',
)]
class ImportCsvCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private Serializer $decoder;
    private Filesystem $filesystem;
    private Connection $connection;
    
    private ActorRepository $actorsRepository;
    private DirectorRepository $directorsRepository;
    private FilmRepository $filmsRepository;


    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->filesystem = new Filesystem();
        $this->entityManager = $entityManager;
        $this->connection = $this->entityManager->getConnection();
        $this->connection->getConfiguration()?->setSQLLogger(null);
        $this->actorsRepository = $this->entityManager->getRepository(Actor::class);
        $this->directorsRepository = $this->entityManager->getRepository(Director::class);
        $this->filmsRepository = $this->entityManager->getRepository(Film::class);

        $this->decoder = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);

        parent::__construct();

    }
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'File path to load');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file_name = $input->getArgument('file');
        $filePath = Path::normalize($file_name);
        $output->writeln("-----Creating Films-----");
        try {

            $this->truncateAll();
            $files = $this->makeCsvChunks($filePath);

            foreach ($files as $file) {

                $rows = $this->decoder->decode(file_get_contents($file), 'csv');
                $numRows = count($rows);

                foreach ($rows as $film) {

                    $this->createDirectorFiles($film);

                    $this->createActorFiles($film);


                    $this->createFilm($film);

                }

                $this->entityManager->flush();
                $this->entityManager->clear();


            }

            $directors = glob('var/temp/directors/*');
            if ($directors) {

                $output->writeln("\r\n\r\n-----Creating directors-----");
                

                $this->createDirectors($directors);


                $this->entityManager->flush();
                $this->entityManager->clear();

            }



            $actors = glob('var/temp/actors/*');
            if ($actors) {

                $output->writeln("\r\n\r\n-----Creating actors-----");
                

                $this->createActors($actors);


                $this->entityManager->flush();
                $this->entityManager->clear();

            }


        } catch (\Doctrine\DBAL\Exception $e) {
            $this->io->error(sprintf('Exception: %s', $e->getMessage()));

            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
    private function createDirectorFiles(mixed $film): void
    {
        if (isset($film['director'])) {
            $filmDirectors = explode(',', $film['director']);
            foreach ($filmDirectors as $director) {

                $fileName = md5(trim($director));
                $resource = fopen(
                    "var/temp/directors/".$fileName.".csv",
                    'ab'
                );
                fwrite($resource, '"'.trim($director).'",'.trim($film['id'])."\r\n");
                fclose($resource);
            }
        }
    }

    private function createActorFiles(mixed $film): void
    {
        if (isset($film['actors'])) {
            $filmActors = explode(',', $film['actors']);
            foreach ($filmActors as $actor) {
                $fileName = md5(trim($actor));
                $resource = fopen(
                    "var/temp/actors/".$fileName.".csv",
                    'ab'
                );
                fwrite($resource, '"'.trim($actor).'",'.trim($film['id'])."\r\n");
                fclose($resource);
            }
        }
    }

    protected function createFilm(array $film): Film
    {
        $existingFilm = new Film();
        if ($film['imdb_title_id']) {
            $existingFilm
                ->setTitle($film['title'])
                ->setPublishedOn($film['date_published'])
                ->setGenre($film['genre'])
                ->setDuration((int)$film['duration'])
                ->setProductionCompany($film['production_company']);
            $this->entityManager->persist($existingFilm);
        }

        return $existingFilm;
    }

    private function createDirectors(array $directorFiles): void
    {
        $batchSize = 0;
        foreach ($directorFiles as $file) {

            $handle = fopen($file, 'rb');
            $existingDirector = new Director();
            if ($relation = fgetcsv($handle)) {
                $existingDirector = new Director();
                $existingDirector->setFullName($relation[0]);
            }
            fclose($handle);

            $this->entityManager->persist($existingDirector);

            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                if ($relation = fgetcsv($handle)) {
                    $filmsRepository = $this->filmsRepository;
                    if ($existingFilm = $filmsRepository->find($relation[1])) {
                        $existingFilm->addDirector($existingDirector);
                    }
                }
            }
            fclose($handle);

            if ($batchSize % 1000 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            $batchSize++;
        }

    }
    private function createActors(array $actorFiles): void
    {
        $batchSize = 0;
        foreach ($actorFiles as $file) {

            $handle = fopen($file, 'rb');
            $existingActor = new Actor();
            if ($relation = fgetcsv($handle)) {
                $existingActor = new Actor();
                $existingActor->setFullName($relation[0]);
            }
            fclose($handle);

            $this->entityManager->persist($existingActor);

            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                if ($relation = fgetcsv($handle)) {
                    $filmsRepository = $this->filmsRepository;
                    if ($existingFilm = $filmsRepository->find($relation[1])) {
                        $existingFilm->addActor($existingActor);
                    }
                }
            }
            fclose($handle);

            if ($batchSize % 1000 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            $batchSize++;
        }
    }
    private function makeCsvChunks(string $filePath): bool|array
    {

        $path = pathinfo($filePath);
        $tempFile = 'var/temp/'.$path['basename'];

        $headers = "id,".implode(",", $this->getHeadersFromCsv($filePath));

        $this->filesystem->mkdir('var/temp');
        $this->filesystem->copy($filePath, $tempFile);
        $this->filesystem->mkdir('var/temp/actors');
        $this->filesystem->mkdir('var/temp/directors');

        $tempInfo = pathinfo($tempFile);
        $tempResource = fopen($tempFile, 'rb');

        $lineCount = 1;

        $fileCount = 1;

        $maxLines = 1000; 
        while (!feof($tempResource)) {

            $fileCounter = str_pad($fileCount, 4, '0', STR_PAD_LEFT);

            $chunk = fopen($tempInfo['dirname'].DIRECTORY_SEPARATOR.$fileCounter."-".$tempInfo['basename'], 'wb');

            if ((int)$fileCounter > 1) {
                fwrite($chunk, trim($headers)."\r\n");
            }
            $id = $id ?? 0;
            while ($lineCount <= $maxLines) {
                $readLine = fgets($tempResource);
                $line = $id.",".$readLine;
                if ((int)$fileCounter === 1 && $lineCount === 1) {
                    $line = "id,".$readLine;
                }
                $content = trim($line);
                fwrite($chunk, $content."\r\n");
                $lineCount++;
                $id++;
            }
            fclose($chunk);
            $lineCount = 1;
            $fileCount++;
        }
        fclose($tempResource);

        $this->filesystem->remove($tempFile);

        return glob($tempInfo['dirname'].DIRECTORY_SEPARATOR."*.*");

    }
    private function getHeadersFromCsv(string $filePath): array
    {
        $f = fopen($filePath, 'rb');
        $line = fgetcsv($f);
        fclose($f);

        return $line;
    }
    private function truncateAll(): void
    {
        $this->clean();

        $connection = $this->connection;
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTables();
        $query = 'SET FOREIGN_KEY_CHECKS = 0;';

        foreach ($tables as $table) {
            $name = $table->getName();
            if ($name !== 'doctrine_migration_versions') {
                $query .= 'TRUNCATE '.$name.';';
            }
        }
        $query .= 'SET FOREIGN_KEY_CHECKS = 1;';
        $connection->executeQuery($query, array(), array());
    }
    private function clean(): void
    {
        $this->filesystem->remove('var/temp');
    }
}
