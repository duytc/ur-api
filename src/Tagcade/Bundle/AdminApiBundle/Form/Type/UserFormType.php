<?php

namespace Tagcade\Bundle\AdminApiBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Tagcade\Bundle\UserBundle\Entity\User;
use Tagcade\Exception\InvalidArgumentException;
use Tagcade\Form\Type\AbstractRoleSpecificFormType;
use Tagcade\Model\User\Role\AdminInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Model\User\UserEntityInterface;
use Tagcade\Service\StringUtilTrait;

class UserFormType extends AbstractRoleSpecificFormType
{
    use StringUtilTrait;

    static $REPORT_SETTINGS_PF_ADTAG_KEY_VALUES = [
        'totalOpportunities',
        'firstOpportunities',
        'impressions',
        'verifiedImpressions',
        'unverifiedImpressions',
        'blankImpressions',
        'voidImpressions',
        'clicks',
        'passbacks',
        'fillRate',
    ];

    static $REPORT_SETTINGS_VR_METRICS_KEYS = [
        'requests',
        'bids',
        'bidRate',
        'impressions',
        'fillRate',
        'errors',
        'errorRate',
        'clicks',
        'clickThroughRate',
        'adTagRequests',
        'adTagImpressions',
        'adTagErrors',
        'adTagBids'
    ];

    static $REPORT_SETTINGS_VR_FILTERS_KEYS = [
        'publisher',
        'demandPartner',
        'adTag',
        'videoDemandAdTag',
    ];

    static $REPORT_SETTINGS_VR_BREAKDOWNS_KEYS = [
        'publisher',
        'demandPartner',
        'adTag',
        'videoDemandAdTag',
        'day'
    ];

    const MODULE_CONFIG = 'moduleConfigs';
    const ABBREVIATION_KEY = 'abbreviation';
    const VIDEO_MODULE = 'MODULE_VIDEO_ANALYTICS';
    const VIDEO_PLAYERS = 'players';

    protected $listPlayers = ['5min', 'defy', 'jwplayer5', 'jwplayer6', 'limelight', 'ooyala', 'scripps', 'ulive'];
    private $oldSettings;

    public function __construct(UserEntityInterface $userRole)
    {
        $this->setUserRole($userRole);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username')
            ->add('plainPassword')
            ->add('firstName')
            ->add('lastName')
            ->add('company')
            ->add('email')
            ->add('phone')
            ->add('city')
            ->add('state')
            ->add('address')
            ->add('postalCode')
            ->add('country')
            ->add('settings')
            ->add('bidders');

        if ($this->userRole instanceof AdminInterface) {
            $builder
                ->add('tagDomain')
                ->add('enabled')
                ->add('enabledModules', 'choice', [
                    'empty_data' => null,
                    'multiple' => true,
                    'choices' => [
                        'MODULE_DISPLAY' => 'Display',
                        'MODULE_VIDEO'         => 'VideoAds',
                        'MODULE_VIDEO_ANALYTICS' => 'Video',
                        'MODULE_ANALYTICS' => 'Analytics',
                        'MODULE_FRAUD_DETECTION' => 'Fraud Detection',
                        'MODULE_UNIFIED_REPORT' => 'Unified Report',
                        'MODULE_HEADER_BIDDING' => 'Header Bidding',
                        'MODULE_RTB' => 'RealTime Bidding'
                    ],
                ])
                ->add('billingRate');
        }

        //validate 'settings' field submitted by publisher
        //also merge all changes to current 'settings' of publisher (ui only submit with patched settings)
        if ($this->userRole instanceof PublisherInterface) {
            $builder->addEventListener(
                FormEvents::PRE_SET_DATA,
                function (FormEvent $event) {
                    $form = $event->getForm();
                }
            );

            $builder->addEventListener(
                FormEvents::POST_SET_DATA,
                function (FormEvent $event) {
                    $form = $event->getForm();
                    /** @var PublisherInterface $publisher */
                    $publisher = $form->getData();
                    $this->oldSettings = $publisher->getSettings();
                });
        }

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                /** @var UserEntityInterface|PublisherInterface $publisher */
                $publisher = $event->getData();
                $form = $event->getForm();

                if ($this->userRole instanceof AdminInterface) {
                    if ($publisher->getId() === null) {
                        $publisher->generateAndAssignUuid();
                    }

                    $modules = $form->get('enabledModules')->getData();

                    if (null !== $modules && is_array($modules)) {
                        $publisher->setEnabledModules($modules);
                    }

                    //validate tag domain if there's
                    $tagDomain = $publisher->getTagDomain();

                    if (!is_array($tagDomain) && $tagDomain !== null) {
                        throw new InvalidArgumentException('expect array object');
                    }

                    if (is_array($tagDomain)) {
                        if (!isset($tagDomain['domain']) && isset($tagDomain['secure'])) {
                            throw new InvalidArgumentException('domain is missing');
                        }

                        if (isset($tagDomain['domain']) && !$this->validateDomain($tagDomain['domain'])) {
                            $form->get('tagDomain')->addError(new FormError(sprintf('"%s" is not a valid domain', $tagDomain['domain'])));
                            return;
                        }

                        if (isset($tagDomain['secure']) && !is_bool($tagDomain['secure'])) {
                            throw new InvalidArgumentException('expect true or false');
                        }
                    }
                } else if ($this->userRole instanceof PublisherInterface) {
                    /** @var PublisherInterface $publisher */
                    $publisher = $form->getData();
                    // this settings is only patched settings
                    $settings = $publisher->getSettings();

                    // 1. validate 'settings' field submitted by publisher
                    if (!$this->validateSettings($settings, $form)) {
                        return; // end process to avoid getting exception (e.g: key not set, ...)
                    }

                    // 2. also merge all changes to current 'settings' of publisher (ui only submit with patched settings)
                    //    if not patch, old_settings (unchanged) will be removed
                    //// checking current settings: not existed or invalid => do nothing, using from ui
                    if ($this->oldSettings !== null
                        && isset($this->oldSettings['view']['report']['performance']['adTag'])
                        && count($this->oldSettings['view']['report']['performance']['adTag']) > 0
                    ) {
                        $newSettings = array_map(function ($settingItem) use ($settings) {
                            $settingItems = $settings['view']['report']['performance']['adTag'];
                            foreach ($settingItems as $idx => $si) {
                                if ($settingItem['key'] === $si['key']) {
                                    return $si;
                                }
                            }

                            return $settingItem;
                        }, $this->oldSettings['view']['report']['performance']['adTag']);

                        $this->oldSettings['view']['report']['performance']['adTag'] = $newSettings;

                        $publisher->setSettings($this->oldSettings);
                    }

                    // videoReport
                    if ($this->oldSettings !== null
                        && isset($this->oldSettings['view']['report']['videoReport']['metrics'])
                    ) {
                        $this->oldSettings['view']['report']['videoReport']['metrics'] = $settings['view']['report']['videoReport']['metrics'];

                        $publisher->setSettings($this->oldSettings);
                    }

                    if ($this->oldSettings !== null
                        && isset($this->oldSettings['view']['report']['videoReport']['filters'])
                    ) {
                        $this->oldSettings['view']['report']['videoReport']['filters'] = $settings['view']['report']['videoReport']['filters'];

                        $publisher->setSettings($this->oldSettings);
                    }

                    if ($this->oldSettings !== null
                        && isset($this->oldSettings['view']['report']['videoReport']['breakdowns'])
                    ) {
                        $this->oldSettings['view']['report']['videoReport']['breakdowns'] = $settings['view']['report']['videoReport']['breakdowns'];

                        $publisher->setSettings($this->oldSettings);
                    }
                }
            }
        );
    }

    /**
     * validate Settings of Publisher
     *
     * @param mixed $settings format as:
     * {
     *      view: {
     *          report: {
     *              performance: {
     *                  adTag: [
     *                      {
     *                          key: "totalOpportunities",
     *                          label: "Network Opportunities",
     *                          show: true
     *                      },
     *                      ...
     *                  ]
     *              },
     *              videoReport: {
     *                 metrics: {
     *                     performance: [],
     *                     unified: [],
     *                     comparison: []
     *                 },
     *                 filters: {
     *                     publisher: [],
     *                     demandPartner: [],
     *                     site: [],
     *                     domain: [],
     *                     adSlot: [],
     *                     adTag: [],
     *                     partnerTag: []
     *                 },
     *                 breakdowns: ['day']
     *              }
     *          }
     *      }
     * }
     *
     * @param FormInterface $form
     * @return bool false if has error
     */
    private function validateSettings($settings, FormInterface $form)
    {
        if (is_null($settings) || !isset($settings['view']['report'])) {
            $form->get('settings')->addError(new FormError("either 'view' or 'report' field is missing!"));
            return false;
        }

        // 1.1 validate performance report setting
        $isValidPRSettings = $this->validatePRSettings($settings, $form);

        // 1.2 validate unified report setting
        $isValidURSettings = $this->validateVRSettings($settings, $form);

        return $isValidPRSettings && $isValidURSettings; // NOTE: fully validate both PR, UR setting
    }

    /**
     * validate Performance Report Settings
     *
     * @param mixed $settings
     * @param FormInterface $form
     * @return bool false if has error
     */
    private function validatePRSettings($settings, FormInterface $form)
    {
        if (!isset($settings['view']['report']['performance']['adTag'])) {
            $form->get('settings')->addError(new FormError("either 'performance' or 'adTag' field is missing!"));
            return false;
        }

        $adTagConfigs = $settings['view']['report']['performance']['adTag'];

        foreach ($adTagConfigs as $adTagConfig) {
            // keys 'key', 'label, 'show' are required
            if (!isset($adTagConfig['key'])
                || !isset($adTagConfig['label'])
                || !isset($adTagConfig['show'])
            ) {
                $form->get('settings')->addError(new FormError("'key or label or show' field is missing!"));
                return false;
            }

            // all values of 'key' need to be supported
            if (!in_array($adTagConfig['key'], self::$REPORT_SETTINGS_PF_ADTAG_KEY_VALUES)) {
                $form->get('settings')->addError(new FormError("key '" . $adTagConfig['key'] . "' is not supported yet!"));
                return false;
            }

            // value 'show' need to be boolean
            if (!is_bool($adTagConfig['show'])) {
                $form->get('settings')->addError(new FormError("value of show for '" . $adTagConfig['key'] . "' must be boolean!"));
                return false;
            }
        }

        return true;
    }

    /**
     * validate Unified Report Settings
     *
     * @param mixed $settings
     * @param FormInterface $form
     * @return bool false if has error
     */
    private function validateVRSettings($settings, FormInterface $form)
    {
        if (!isset($settings['view']['report']['videoReport']['metrics'])
            || !isset($settings['view']['report']['videoReport']['filters'])
            || !isset($settings['view']['report']['videoReport']['breakdowns'])
        ) {
            $form->get('settings')->addError(new FormError("either 'videoReport' or 'metrics' or 'filters' or 'breakdowns' field is missing!"));
            return false;
        }

        // 1. validate metrics
        $vrMetricConfigs = $settings['view']['report']['videoReport']['metrics'];

        if (!is_array($vrMetricConfigs)) {
            $form->get('settings')->addError(new FormError(sprintf('expect an array of metrics')));
            return false;
        }

        $diff = array_diff($vrMetricConfigs, self::$REPORT_SETTINGS_VR_METRICS_KEYS);
        if (count($diff) > 0) {
            $form->get('settings')->addError(new FormError(sprintf('metrics "%s" is currently not supported', implode(',', $diff))));
            return false;
        }

        // 2. validate filters
        $vrFilterConfigs = $settings['view']['report']['videoReport']['filters'];

        if (!is_array($vrFilterConfigs)) {
            $form->get('settings')->addError(new FormError(sprintf('expect an array of filters')));
            return false;
        }

        foreach($vrFilterConfigs as $key => $vrFilterConfig) {
            if (!in_array($key, self::$REPORT_SETTINGS_VR_FILTERS_KEYS)) {
                $form->get('settings')->addError(new FormError(sprintf('filters "%s" is currently not supported', $key)));
                return false;
            }

            if (!is_array($vrFilterConfigs[$key])) {
                $form->get('settings')->addError(new FormError(sprintf('expect "%s" to be an array', $vrFilterConfigs[$key])));
                return false;
            }
        }

        // 3. validate breakdowns
        $vrBreakdownConfigs = $settings['view']['report']['videoReport']['breakdowns'];

        if (!is_array($vrBreakdownConfigs)) {
            $form->get('settings')->addError(new FormError(sprintf('expect an array of breakdowns')));
            return false;
        }

        $diff = array_diff($vrBreakdownConfigs, self::$REPORT_SETTINGS_VR_BREAKDOWNS_KEYS);
        if (count($diff) > 0) {
            $form->get('settings')->addError(new FormError(sprintf('breakdowns "%s" is currently not supported', implode(',', $diff))));
            return false;
        }

        return true;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'validation_groups' => ['Admin', 'Default'],
            ]);
    }

    public function getName()
    {
        return 'tagcade_form_admin_api_user';
    }
}