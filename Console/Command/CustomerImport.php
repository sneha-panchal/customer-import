<?php

declare(strict_types=1);

namespace SnehaPanchal\CustomerImport\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Model\AccountManagement;

class CustomerImport extends Command
{

    const NAME_ARGUMENT = "profile-name";
    const SOURCE_ARGUMENT = "source";

    private $filesystem;
    private $customer;
    private $state;
    private $storeManager;
    private $csvProcessor;
    private $file;
    private $json;
    private $accountManagement;

    public function __construct(
        Filesystem $filesystem,
        CustomerInterfaceFactory $customer,
        State $state,
        StoreManagerInterface $storeManager,
        Csv $csvProcessor,
        File $file,
        Json $json,
        AccountManagement $accountManagement
    ) {
        parent::__construct();
        $this->filesystem = $filesystem;
        $this->customer = $customer;
        $this->state = $state;
        $this->storeManager = $storeManager;
        $this->csvProcessor = $csvProcessor;
        $this->file = $file;
        $this->json = $json;
        $this->accountManagement = $accountManagement;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) : ?int 

    {
        $name = $input->getArgument(self::NAME_ARGUMENT);
        $source = $input->getArgument(self::SOURCE_ARGUMENT);
        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $importfile = $mediaDir->getAbsolutePath() . 'customerimport/'. $source;

        $storeId = $this->storeManager->getStore()->getId();
         
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

        $this->state->setAreaCode(Area::AREA_GLOBAL);

        if ($this->file->isExists($importfile)) {
            if($name == 'sample-csv'){
                $this->csvProcessor->setDelimiter(",");
                //get data as an array
                $dataArray = $this->csvProcessor->getData($importfile);
                $data = array_slice($dataArray, 1);
            } else if($name == 'sample-json'){
                $jsonData = file_get_contents($importfile);
                $dataArray = $this->json->unserialize($jsonData);
                $data = [];
                foreach ($dataArray as $key => $value) {
                    $data[$key] = array_values($value);
                }
            }

        } else {
            $output->writeln('<error>Please put your '.$source.' file inside "pub/media/customerimport" folder</error>', OutputInterface::OUTPUT_NORMAL);
            return Cli::RETURN_FAILURE;
        }

        if(count($data) > 0){
            foreach($data as $customerDetail){
                try {
                    $customer = $this->customer->create();
                    $customer->setWebsiteId($websiteId);
                    $customer->setEmail($customerDetail[2]);
                    $customer->setFirstname($customerDetail[0]);
                    $customer->setLastname($customerDetail[1]);
                    $this->accountManagement->createAccount($customer);
                    
                } catch (Exception $e) {
                    echo $e->getMessage();
                }  
            }
            $output->writeln(count($data).' Custoomer(s) created from the '.$source.' file', OutputInterface::OUTPUT_NORMAL);

            return Cli::RETURN_SUCCESS;  

        }else{
            $output->writeln('<error>There is no any customer data exist in '.$source.' file</error>', OutputInterface::OUTPUT_NORMAL);
            return Cli::RETURN_FAILURE;
        }

    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("customer:import");
        $this->setDescription("Import Customer from csv");
        $this->setDefinition([
            new InputArgument(self::NAME_ARGUMENT, InputArgument::OPTIONAL, "Profile Name"),
            new InputArgument(self::SOURCE_ARGUMENT, InputArgument::OPTIONAL, "Source")
        ]);
        parent::configure();
    }

}

