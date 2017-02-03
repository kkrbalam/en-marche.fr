<?php

namespace Test\AppBundle\Controller\ArgumentValueResolver;

use AppBundle\Controller\ArgumentValueResolver\MembershipOnBoardingResolver;
use AppBundle\Donation\DonationRequest;
use AppBundle\Donation\DonationRequestFactory;
use AppBundle\Entity\Adherent;
use AppBundle\Membership\MembershipOnBoardingInterface;
use AppBundle\Membership\OnBoarding\RegisteringAdherent;
use AppBundle\Membership\OnBoarding\RegisteringDonation;
use AppBundle\Repository\AdherentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class MembershipOnBoardingResolverTest extends \PHPUnit_Framework_TestCase
{
    /** @var MembershipOnBoardingResolver */
    private $resolver;

    /** @var AdherentRepository|\PHPUnit_Framework_MockObject_MockObject */
    private $adherentRepository;

    /** @var DonationRequestFactory */
    private $donationRequestFactory;

    public function testSupportsWithoutSession()
    {
        $this->assertFalse(
            $this->resolver->supports(Request::create('/'), $this->createArgumentMetadata())
        );

        $this->assertFalse(
            $this->resolver->supports(Request::create('/'), $this->createArgumentMetadata(RegisteringAdherent::class))
        );

        $this->assertFalse(
            $this->resolver->supports(Request::create('/'), $this->createArgumentMetadata(RegisteringDonation::class))
        );
    }

    public function testSupports()
    {
        $this->assertFalse(
            $this->resolver->supports($this->createRequest(), $this->createArgumentMetadata())
        );

        $this->assertTrue(
            $this->resolver->supports($this->createRequest(), $this->createArgumentMetadata(RegisteringAdherent::class))
        );

        $this->assertTrue(
            $this->resolver->supports($this->createRequest(), $this->createArgumentMetadata(RegisteringDonation::class))
        );
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testResolveWithNoAdherentId()
    {
        $this->adherentRepository
            ->expects($this->never())
            ->method('find');

        foreach ($this->resolver->resolve($this->createRequest(), $this->createArgumentMetadata()) as $result) {
        }
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testResolveWithWrongAdherentId()
    {
        $wrongID = 'fake';

        $this->adherentRepository
            ->expects($this->once())
            ->method('find')
            ->with($wrongID)
            ->willReturn(null);

        foreach ($this->resolver->resolve($this->createRequest($wrongID), $this->createArgumentMetadata()) as $result) {
        }
    }

    public function testResolveWithRegisteringAdherent()
    {
        $adherent = $this->getMockBuilder(Adherent::class)->disableOriginalConstructor()->getMock();

        $this->adherentRepository
            ->expects($this->once())
            ->method('find')
            ->with('id')
            ->willReturn($adherent);

        $results = $this->resolver->resolve(
            $this->createRequest('id'),
            $this->createArgumentMetadata(RegisteringAdherent::class)
        );

        foreach ($results as $result) {
            $this->assertInstanceOf(RegisteringAdherent::class, $result);
            $this->assertSame($adherent, $result->getAdherent());
        }
    }

    public function testResolveWithRegisteringDonation()
    {
        $this->adherentRepository
            ->expects($this->once())
            ->method('find')
            ->with('id')
            ->willReturn($this->getMockBuilder(Adherent::class)->disableOriginalConstructor()->getMock());

        $results = $this->resolver->resolve(
            $this->createRequest('id'),
            $this->createArgumentMetadata(RegisteringDonation::class)
        );

        foreach ($results as $result) {
            $this->assertInstanceOf(RegisteringDonation::class, $result);
            $this->assertInstanceOf(DonationRequest::class, $donation = $result->getDonationRequest());
        }
    }

    public function setUp()
    {
        parent::setUp();

        $this->adherentRepository = $this->getMockBuilder(AdherentRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->donationRequestFactory = new DonationRequestFactory();

        $this->resolver = new MembershipOnBoardingResolver($this->adherentRepository, $this->donationRequestFactory);
    }

    public function tearDown()
    {
        $this->adherentRepository = null;
        $this->donationRequestFactory = null;

        $this->resolver = null;

        parent::tearDown();
    }

    private function createArgumentMetadata(string $type = ''): ArgumentMetadata
    {
        return new ArgumentMetadata('arg', $type, false, false, null);
    }

    private function createRequest(?string $newAdherentId = null): Request
    {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());

        if (null !== $newAdherentId) {
            $session->set(MembershipOnBoardingInterface::NEW_ADHERENT_ID, $newAdherentId);
        }

        $request->setSession($session);

        return $request;
    }
}