<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="apmbs_cabanes")
 * @ORM\Entity()
 */
class Cabane {

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="from_email", type="string", length=255)
     */
    protected $fromEmail;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="calendar_id", type="string", length=255)
     */
    protected $calendarId;

    /**
     * @var APMBSReservation[]
     * @ORM\OneToMany(targetEntity="App\Entity\APMBSReservation", mappedBy="cabane")
     */
    protected $reservations;

    /**
     * @var ArrayCollection
     * @ORM\ManyToMany(targetEntity="App\Entity\Intendant", inversedBy="cabanes")
     * @ORM\JoinTable(name="apmbs_cabanes_intendants")
     */
    protected $intendants;

    /**
     * @var ArrayCollection
     * @ORM\ManyToMany(targetEntity="App\Entity\CabaneTimePeriod")
     */
    protected $timePeriods;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="availability_rule", type="text")
     */
    protected $availabilityRule;

    /**
     * @var string
     * @ORM\Column(name="disabled_dates", type="text", nullable=true)
     */
    protected $disabledDates;

    /**
     * @var string
     * @ORM\Column(name="prices", type="text", nullable=true)
     */
    protected $prices;

    /**
     * @var string
     * @ORM\Column(name="google_form_url", type="string", length=255, nullable=true)
     */
    protected $googleFormUrl;

    // EMAILS

    /**
     * @var string
     * @ORM\Column(name="received_email", type="text", nullable=true)
     */
    protected $receivedEmail;

    /**
     * @var string
     * @ORM\Column(name="rejected_email", type="text", nullable=true)
     */
    protected $rejectedEmail;

    /**
     * @var string
     * @ORM\Column(name="correction_email", type="text", nullable=true)
     */
    protected $correctionEmail;

    /**
     * @var string
     * @ORM\Column(name="confirmed_email", type="text", nullable=true)
     */
    protected $confirmedEmail;

    /**
     * @var string
     * @ORM\Column(name="cancelled_email", type="text", nullable=true)
     */
    protected $cancelledEmail;

    /**
     * @var string
     * @ORM\Column(name="invoice_email", type="text", nullable=true)
     */
    protected $invoiceEmail;

    /**
     * @var string
     * @ORM\Column(name="close_email", type="text", nullable=true)
     */
    protected $closeEmail;

    /**
     * @var string
     * @ORM\Column(name="price_method", type="text", nullable=true)
     */
    protected $priceMethod;

    /**
     * @var string
     * @ORM\Column(name="conditions", type="text", nullable=true)
     */
    protected $conditions;

    public function __construct() {
        $this->reservations = new ArrayCollection();
        $this->intendants = new ArrayCollection();
        $this->timePeriods = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNom(): string
    {
        return $this->nom;
    }

    /**
     * @param string $nom
     */
    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    /**
     * @return string
     */
    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    /**
     * @param string $calendarId
     */
    public function setCalendarId(string $calendarId): void
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return APMBSReservation[]
     */
    public function getReservations(): iterable
    {
        return $this->reservations;
    }

    /**
     * @return Intendant[]
     */
    public function getIntendants() {
        return $this->intendants->toArray();
    }

    /**
     * @param Intendant $intendant
     */
    public function addIntendant(Intendant $intendant): void {
        $this->intendants[] = $intendant;
    }

    /**
     * @param Intendant $intendant
     */
    public function removeIntendant(Intendant $intendant): void {
        $this->intendants->removeElement($intendant);
    }

    /**
     * @return string
     */
    public function getGoogleFormUrl(): string {
        return $this->googleFormUrl;
    }

    /**
     * @param string $googleFormUrl
     */
    public function setGoogleFormUrl(string $googleFormUrl): void {
        $this->googleFormUrl = $googleFormUrl;
    }

    /**
     * @return string
     */
    public function getAvailabilityRule(): string {
        return $this->availabilityRule;
    }

    /**
     * @param string $availabilityRule
     */
    public function setAvailabilityRule(string $availabilityRule): void {
        $this->availabilityRule = $availabilityRule;
    }

    public function getReceivedEmail() {
        return $this->receivedEmail;
    }

    public function getRejectedEmail() {
        return $this->rejectedEmail;
    }

    public function getCorrectionEmail() {
        return $this->correctionEmail;
    }

    public function getConfirmedEmail() {
        return $this->confirmedEmail;
    }

    public function getCancelledEmail() {
        return $this->cancelledEmail;
    }

    public function setReceivedEmail($receivedEmail) {
        $this->receivedEmail = $receivedEmail;
    }

    public function setRejectedEmail($rejectedEmail) {
        $this->rejectedEmail = $rejectedEmail;
    }

    public function setCorrectionEmail($correctionEmail) {
        $this->correctionEmail = $correctionEmail;
    }

    public function setConfirmedEmail($confirmedEmail) {
        $this->confirmedEmail = $confirmedEmail;
    }

    public function setCancelledEmail($cancelledEmail) {
        $this->cancelledEmail = $cancelledEmail;
    }

    public function getPrices() {
        return $this->prices;
    }

    public function setPrices($prices) {
        $this->prices = $prices;
    }

    public function getTimePeriods() {
        return $this->timePeriods->toArray();
    }

    public function addTimePeriod(CabaneTimePeriod $timePeriod) {
        $this->timePeriods[] = $timePeriod;
    }

    public function removeTimePeriod(CabaneTimePeriod $timePeriod) {
        $this->timePeriods->removeElement($timePeriod);
    }

    public function getDisabledDates() {
        return $this->disabledDates;
    }

    public function setDisabledDates($disabledDates) {
        $this->disabledDates = $disabledDates;
    }

    public function getFromEmail() {
        return $this->fromEmail;
    }

    public function setFromEmail($fromEmail) {
        $this->fromEmail = $fromEmail;
    }

    public function getPriceMethod() {
        return $this->priceMethod;
    }

    public function setPriceMethod($priceMethod) {
        $this->priceMethod = $priceMethod;
    }

    public function getConditions() {
        return $this->conditions;
    }

    public function setConditions($conditions) {
        $this->conditions = $conditions;
    }

    public function getConditionsList() {
        if (!$this->conditions) {
            return [];
        }
        
        return explode("\n", $this->conditions);
    }

    public function getInvoiceEmail() {
        return $this->invoiceEmail;
    }

    public function setInvoiceEmail($invoiceEmail) {
        $this->invoiceEmail = $invoiceEmail;
    }

    public function getCloseEmail() {
        return $this->closeEmail;
    }

    public function setCloseEmail($closeEmail) {
        $this->closeEmail = $closeEmail;
    }
}