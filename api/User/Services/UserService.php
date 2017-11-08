<?php

namespace Api\User\Services;

use Exception;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Events\Dispatcher;

use Api\Extension\Models\Extension;
use Api\Extension\Services\ExtensionService;

use Api\User\Exceptions\InvalidGroupException;
use Api\User\Exceptions\UserNotFoundException;
use Api\User\Exceptions\DomainNotFoundException;
use Api\User\Exceptions\UserExistsException;
use Api\User\Exceptions\EmailExistsException;
use Api\User\Exceptions\ActivationHashNotFoundException;
use Api\User\Exceptions\ActivationHashWrongException;


use Api\User\Events\UserWasCreated;
use Api\User\Events\UserWasDeleted;
use Api\User\Events\UserWasUpdated;

use Api\User\Repositories\GroupRepository;
use Api\User\Repositories\UserRepository;
use Api\User\Repositories\DomainRepository;
use Api\User\Repositories\ContactRepository;
use Api\User\Repositories\Contact_emailRepository;
use Api\Extension\Repositories\ExtensionRepository;

use App\Traits\OneToManyRelationCRUD;

use Illuminate\Support\Facades\Auth;

class UserService
{
    use OneToManyRelationCRUD;

    private $auth;

    private $database;

    private $dispatcher;

    private $groupRepository;

    private $userRepository;

    private $contactRepository;

    private $contact_emailRepository;

    private $extensionRepository;

    private $domainRepository;

    private $extensionService;

    private $scope;

    public function __construct(
        AuthManager $auth,
        DatabaseManager $database,
        Dispatcher $dispatcher,
        GroupRepository $groupRepository,
        UserRepository $userRepository,
        ContactRepository $contactRepository,
        Contact_emailRepository $contact_emailRepository,
        ExtensionRepository $extensionRepository,
        DomainRepository $domainRepository,
        ExtensionService $extensionService
    ) {
        $this->auth = $auth;
        $this->database = $database;
        $this->dispatcher = $dispatcher;
        $this->groupRepository = $groupRepository;
        $this->userRepository = $userRepository;
        $this->contactRepository = $contactRepository;
        $this->contact_emailRepository = $contact_emailRepository;
        $this->extensionRepository = $extensionRepository;
        $this->domainRepository = $domainRepository;
        $this->extensionService = $extensionService;

        $this->setScope();
    }

    public function getMe($options = [])
    {
        //return Auth::user();
        $class = Extension::class;
        $class::$staticMakeVisible = ['password'];
				return $this->userRepository->getWhere('user_uuid', Auth::user()->user_uuid)->first();
    }

    public function getAll($options = [])
    {
				return $this->userRepository->getWhereArray(['domain_uuid' => Auth::user()->domain_uuid, 'user_enabled' => 'true']);
    }

    public function getById($userId, array $options = [])
    {
        return $this->userRepository->getWhere('user_uuid', $userId)->first();

        // ~ $user = $this->getRequestedUser($userId, $options);

        // ~ return $user;
    }

    /**
     * Creates a user
     *
     * Creates a user including all tied tables
     *
     * @param   array  $data     Data to create a user
     *
     * @return   type  Description
     */
    public function create($data)
    {
        $this->database->beginTransaction();

        try {
            // If it's a team registration, we just create the first user in the domain.
            if ($data['isTeam'])
            {

            }
            // Otherwise we check if the username of email exists in the domain
            else
            {
              // Check if domain exists
              $domain = $this->domainRepository->getWhere('domain_name', $data['domain_name']);
              $domain = $domain->first();

              // Get user by domain and username - create only if there is no a user with such a name
              $user = $this->userRepository->getWhereArray([
                'domain_uuid' => $domain['domain_uuid'],
                'username' => $data['username'],
              ]);


              // Check for the email in the current domain
              $contact_email = $this->contact_emailRepository->getWhereArray([
                'domain_uuid' => $domain['domain_uuid'],
                'email_address' => $data['email'],
              ]);

              $data['domain_uuid'] = $domain->getAttribute('domain_uuid');
            }


              if ($user->count() > 0)
              {
              

            
            $extension_number = (int) $data['exten'];
            $effective_caller_id_name = $data['effective_caller_id_name'];
            $effective_caller_id_number = $extension_number;
            $outbound_caller_id_name = $data['outbound_caller_id_name'];
            $outbound_caller_id_number = $data['outbound_caller_id_number'];
            
            
            $extdata = $this->extensionRepository->getWhere('extension', $extension_number);
            $extensionId = $extdata->first()->extension_uuid;
           

            $extension = $this->extensionService->update($extensionId,['effective_caller_id_name' => $effective_caller_id_name,'effective_caller_id_number' => $effective_caller_id_number, 'outbound_caller_id_name' => $outbound_caller_id_name,'outbound_caller_id_number' => $outbound_caller_id_number, 'description' => $effective_caller_id_name]);


          //  $this->extensionService->setOneToManyRelations('Users', $extensionId, [$user->first()->user_uuid]);
          //  $user->setRelation('extension', $extension);



            $user2 = $this->getRequestedUser($user->first()->user_uuid);

            $this->dispatcher->fire(new UserWasUpdated($user2));

            

            }

            else{
            // Create a contact
            $data['contact_type'] = 'user';
            $data['contact_nickname'] = $data['email'];

            $contact = $this->contactRepository->create($data);

            // Hide the field in the output
            $contact->addHidden(['domain_uuid']);

            // Create a email for the contact
            $data['contact_uuid'] = $contact->getAttribute('contact_uuid');

            $data['email_primary'] = 1;
            $data['email_address'] = $data['email'];

            $contact_email = $this->contact_emailRepository->create($data);

            // Hide the field in the output
            $contact_email->addHidden(['domain_uuid', 'contact_uuid']);

            // Finally create the user and hide an unneded field in the output
            $user = $this->userRepository->create($data);

            $user->addHidden(['domain_uuid', 'contact_uuid']);

            // Get group name
            $group = $this->groupRepository->getWhere('group_name', $data['group_name']);
            $data['group_uuid'] = $group->first()->group_uuid;

            // Assign the newly created user to the group
            $this->setOneToManyRelations('Groups', $user->user_uuid, [$data['group_uuid']]);

            // Set relations to later output it
            $contact->setRelation('contact_email', $contact_email);
            $user->setRelation('contact', $contact);

            $password = $data['password'];
            
            $extension_number = (int) $data['exten'];
            $effective_caller_id_name = $data['effective_caller_id_name'];
            $effective_caller_id_number = $extension_number;
            $user_record = 'all';
            $outbound_caller_id_name = $data['outbound_caller_id_name'];
            $outbound_caller_id_number = $data['outbound_caller_id_number'];
            $directory_full_name = $data['effective_caller_id_name'];


            $extension = $this->extensionService->create(['extension' => $extension_number, 'password' => $password, 'effective_caller_id_name' => $effective_caller_id_name,'effective_caller_id_number' => $effective_caller_id_number, 'outbound_caller_id_name' => $outbound_caller_id_name,'outbound_caller_id_number' => $outbound_caller_id_number, 'user_record' => $user_record, 'description' => $effective_caller_id_name, 'directory_full_name' => $directory_full_name], $user);
            
            //$extension = $this->extensionService->create(['extension' => $extension_number, 'password' => $password], $user);
            
            $extension->makeVisible('password');
            $this->extensionService->setOneToManyRelations('Users', $extension->extension_uuid, [$user->user_uuid]);
            $user->setRelation('extension', $extension);

            $this->dispatcher->fire(new UserWasCreated($user));
            }

        } catch (Exception $e) {
            $this->database->rollBack();

            throw $e;
        }

        $this->database->commit();

        return $user;
    }

    public function activate($hash)
    {
        // Since there is no a field dedicated to activation, Gruz have decided to use the quazi-boolean user_enabled field.
        // FusionPBX recognizes non 'true' as FALSE. So our hash in the user_enabled field is treated as FALSE till user is activated.
        if (strlen($hash) != 32) {
            throw new ActivationHashWrongException();
        }

        $user = $this->userRepository->getWhere('user_enabled', $hash)->first();

        if (is_null($user)) {
            throw new ActivationHashNotFoundException();
        }

        $data = [];
        $data['user_enabled'] = 'true';

        $this->database->beginTransaction();

        try {
            $this->userRepository->update($user, $data);

          

            $this->dispatcher->fire(new UserWasUpdated($user));
        } catch (Exception $e) {
            $this->database->rollBack();

            throw $e;
        }

        $this->database->commit();

        $response = [
          'message' => __('User activated'),
          'user' => $user
        ];

        return $response;
    }

    public function update($userId, array $data)
    {
        $user = $this->getRequestedUser($userId);

        $this->database->beginTransaction();

        try {
            $this->userRepository->update($user, $data);


            $this->dispatcher->fire(new UserWasUpdated($user));
        } catch (Exception $e) {
            $this->database->rollBack();

            throw $e;
        }

        $this->database->commit();

        return $user;
    }

    public function delete($userId)
    {
        $user = $this->getRequestedUser($userId);

        $this->database->beginTransaction();

        try {
            $this->userRepository->delete($userId);

            $this->dispatcher->fire(new UserWasDeleted($user));
        } catch (Exception $e) {
            $this->database->rollBack();

            throw $e;
        }

        $this->database->commit();
    }


}
