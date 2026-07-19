<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Command;

use OCA\Berichtsheft\BackgroundJob\AusbilderDigestJob;
use OCA\Berichtsheft\BackgroundJob\LehrjahrAbfrageJob;
use OCA\Berichtsheft\BackgroundJob\WeeklyReminderJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ background-job:execute --force-execute` umgeht Nextclouds eigene
 * Intervall-/Reservierungssperre, NICHT aber die selbstgebaute
 * Wochentag/Uhrzeit-Sperre in run() dieser drei Jobs (Plan Abschnitt 7).
 * Dieser Befehl ruft die Job-Logik direkt ueber ihre oeffentliche
 * laufeFuer()-Methode auf und kann die Zeitsperre optional ignorieren -
 * praktisch zum Testen, ohne auf einen echten Montag/Stichtag warten zu
 * muessen.
 */
class DebugRunJob extends Command {
	public function __construct(
		private WeeklyReminderJob $weeklyReminderJob,
		private AusbilderDigestJob $ausbilderDigestJob,
		private LehrjahrAbfrageJob $lehrjahrAbfrageJob,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('berichtsheft:debug-run-job')
			->setDescription('Fuehrt einen der zeitgesteuerten Berichtsheft-Jobs sofort aus, optional unter Umgehung seiner Wochentag/Uhrzeit-Sperre (nur zum Testen).')
			->addArgument('job', InputArgument::REQUIRED, 'weekly-reminder | ausbilder-digest | lehrjahr-abfrage')
			->addOption('ignoriere-zeitfenster', null, InputOption::VALUE_NONE, 'Wochentag/Uhrzeit-Sperre fuer diesen Lauf ignorieren')
			->addOption('heute', null, InputOption::VALUE_REQUIRED, 'Datum Y-m-d simulieren statt des echten heutigen Datums');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$job = $input->getArgument('job');
		$ignoriere = (bool)$input->getOption('ignoriere-zeitfenster');
		$heute = $input->getOption('heute') ?? date('Y-m-d');

		switch ($job) {
			case 'weekly-reminder':
				$this->weeklyReminderJob->laufeFuer($heute, (int)(new \DateTimeImmutable($heute))->format('N'), $ignoriere);
				break;
			case 'ausbilder-digest':
				$this->ausbilderDigestJob->laufeFuer($heute, (int)(new \DateTimeImmutable($heute))->format('N'), (int)date('H'), $ignoriere);
				break;
			case 'lehrjahr-abfrage':
				$this->lehrjahrAbfrageJob->laufeFuer($heute, $ignoriere);
				break;
			default:
				$output->writeln('<error>Unbekannter Job: ' . $job . ' (erwartet: weekly-reminder | ausbilder-digest | lehrjahr-abfrage)</error>');
				return 1;
		}

		$output->writeln("<info>Job '$job' fuer $heute ausgefuehrt" . ($ignoriere ? ' (Zeitfenster ignoriert)' : '') . '.</info>');
		return 0;
	}
}
