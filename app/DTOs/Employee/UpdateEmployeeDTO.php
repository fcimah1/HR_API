<?php

namespace App\DTOs\Employee;

class UpdateEmployeeDTO
{
    public function __construct(
        public int $user_id,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $email = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?int $department_id = null,
        public ?int $designation_id = null,
        public ?string $contact_number = null,
        public ?string $gender = null,
        public ?float $basic_salary = null,
        public ?string $date_of_joining = null,
        public ?string $date_of_birth = null,
        public ?string $marital_status = null,
        public ?string $blood_group = null,
        public ?string $address_1 = null,
        public ?string $address_2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zipcode = null,
        public ?string $country = null,
        public ?string $bio = null,
        public ?string $experience = null,
        public ?float $hourly_rate = null,
        public ?string $salary_type = null,
        public ?string $salary_payment_method = null,
        public ?int $currency_id = null,
        public ?string $role_description = null,
        public ?string $contract_end = null,
        public ?string $date_of_leaving = null,
        public ?int $religion_id = null,
        public ?int $citizenship_id = null,
        public ?string $fb_profile = null,
        public ?string $twitter_profile = null,
        public ?string $gplus_profile = null,
        public ?string $linkedin_profile = null,
        public ?string $account_title = null,
        public ?string $account_number = null,
        public ?string $bank_name = null,
        public ?string $iban = null,
        public ?string $swift_code = null,
        public ?string $bank_branch = null,
        public ?string $contact_full_name = null,
        public ?string $contact_phone_no = null,
        public ?string $contact_email = null,
        public ?string $contact_address = null,
        public ?string $employee_idnum = null,
        public ?string $passport_no = null,
        public ?string $passport_date = null,
        public ?int $branch_id = null,
        public ?string $biotime_id = null,
        public ?bool $is_active = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: (int) $data['user_id'],
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
            email: $data['email'] ?? null,
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            department_id: isset($data['department_id']) ? (int) $data['department_id'] : null,
            designation_id: isset($data['designation_id']) ? (int) $data['designation_id'] : null,
            contact_number: $data['contact_number'] ?? null,
            gender: $data['gender'] ?? null,
            basic_salary: isset($data['basic_salary']) ? (float) $data['basic_salary'] : null,
            date_of_joining: $data['date_of_joining'] ?? null,
            date_of_birth: $data['date_of_birth'] ?? null,
            marital_status: $data['marital_status'] ?? null,
            blood_group: $data['blood_group'] ?? null,
            address_1: $data['address_1'] ?? null,
            address_2: $data['address_2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipcode: $data['zipcode'] ?? null,
            country: $data['country'] ?? null,
            bio: $data['bio'] ?? null,
            experience: $data['experience'] ?? null,
            hourly_rate: isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null,
            salary_type: $data['salary_type'] ?? null,
            salary_payment_method: $data['salary_payment_method'] ?? null,
            currency_id: isset($data['currency_id']) ? (int) $data['currency_id'] : null,
            role_description: $data['role_description'] ?? null,
            contract_end: $data['contract_end'] ?? null,
            date_of_leaving: $data['date_of_leaving'] ?? null,
            religion_id: isset($data['religion_id']) ? (int) $data['religion_id'] : null,
            citizenship_id: isset($data['citizenship_id']) ? (int) $data['citizenship_id'] : null,
            fb_profile: $data['fb_profile'] ?? null,
            twitter_profile: $data['twitter_profile'] ?? null,
            gplus_profile: $data['gplus_profile'] ?? null,
            linkedin_profile: $data['linkedin_profile'] ?? null,
            account_title: $data['account_title'] ?? null,
            account_number: $data['account_number'] ?? null,
            bank_name: $data['bank_name'] ?? null,
            iban: $data['iban'] ?? null,
            swift_code: $data['swift_code'] ?? null,
            bank_branch: $data['bank_branch'] ?? null,
            contact_full_name: $data['contact_full_name'] ?? null,
            contact_phone_no: $data['contact_phone_no'] ?? null,
            contact_email: $data['contact_email'] ?? null,
            contact_address: $data['contact_address'] ?? null,
            employee_idnum: $data['employee_idnum'] ?? null,
            passport_no: $data['passport_no'] ?? null,
            passport_date: $data['passport_date'] ?? null,
            branch_id: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            biotime_id: $data['biotime_id'] ?? null,
            is_active: isset($data['is_active']) ? (bool) $data['is_active'] : null
        );
    }

    public function toArray(): array
    {
        $data = ['user_id' => $this->user_id];

        if ($this->first_name !== null) $data['first_name'] = $this->first_name;
        if ($this->last_name !== null) $data['last_name'] = $this->last_name;
        if ($this->email !== null) $data['email'] = $this->email;
        if ($this->username !== null) $data['username'] = $this->username;
        if ($this->password !== null) $data['password'] = $this->password;
        if ($this->department_id !== null) $data['department_id'] = $this->department_id;
        if ($this->designation_id !== null) $data['designation_id'] = $this->designation_id;
        if ($this->contact_number !== null) $data['contact_number'] = $this->contact_number;
        if ($this->gender !== null) $data['gender'] = $this->gender;
        if ($this->basic_salary !== null) $data['basic_salary'] = $this->basic_salary;
        if ($this->date_of_joining !== null) $data['date_of_joining'] = $this->date_of_joining;
        if ($this->date_of_birth !== null) $data['date_of_birth'] = $this->date_of_birth;
        if ($this->marital_status !== null) $data['marital_status'] = $this->marital_status;
        if ($this->blood_group !== null) $data['blood_group'] = $this->blood_group;
        if ($this->address_1 !== null) $data['address_1'] = $this->address_1;
        if ($this->address_2 !== null) $data['address_2'] = $this->address_2;
        if ($this->city !== null) $data['city'] = $this->city;
        if ($this->state !== null) $data['state'] = $this->state;
        if ($this->zipcode !== null) $data['zipcode'] = $this->zipcode;
        if ($this->country !== null) $data['country'] = $this->country;
        if ($this->bio !== null) $data['bio'] = $this->bio;
        if ($this->experience !== null) $data['experience'] = $this->experience;
        if ($this->hourly_rate !== null) $data['hourly_rate'] = $this->hourly_rate;
        if ($this->salary_type !== null) $data['salary_type'] = $this->salary_type;
        if ($this->salary_payment_method !== null) $data['salary_payment_method'] = $this->salary_payment_method;
        if ($this->currency_id !== null) $data['currency_id'] = $this->currency_id;
        if ($this->role_description !== null) $data['role_description'] = $this->role_description;
        if ($this->contract_end !== null) $data['contract_end'] = $this->contract_end;
        if ($this->date_of_leaving !== null) $data['date_of_leaving'] = $this->date_of_leaving;
        if ($this->religion_id !== null) $data['religion_id'] = $this->religion_id;
        if ($this->citizenship_id !== null) $data['citizenship_id'] = $this->citizenship_id;
        if ($this->fb_profile !== null) $data['fb_profile'] = $this->fb_profile;
        if ($this->twitter_profile !== null) $data['twitter_profile'] = $this->twitter_profile;
        if ($this->gplus_profile !== null) $data['gplus_profile'] = $this->gplus_profile;
        if ($this->linkedin_profile !== null) $data['linkedin_profile'] = $this->linkedin_profile;
        if ($this->account_title !== null) $data['account_title'] = $this->account_title;
        if ($this->account_number !== null) $data['account_number'] = $this->account_number;
        if ($this->bank_name !== null) $data['bank_name'] = $this->bank_name;
        if ($this->iban !== null) $data['iban'] = $this->iban;
        if ($this->swift_code !== null) $data['swift_code'] = $this->swift_code;
        if ($this->bank_branch !== null) $data['bank_branch'] = $this->bank_branch;
        if ($this->contact_full_name !== null) $data['contact_full_name'] = $this->contact_full_name;
        if ($this->contact_phone_no !== null) $data['contact_phone_no'] = $this->contact_phone_no;
        if ($this->contact_email !== null) $data['contact_email'] = $this->contact_email;
        if ($this->contact_address !== null) $data['contact_address'] = $this->contact_address;
        if ($this->employee_idnum !== null) $data['employee_idnum'] = $this->employee_idnum;
        if ($this->passport_no !== null) $data['passport_no'] = $this->passport_no;
        if ($this->passport_date !== null) $data['passport_date'] = $this->passport_date;
        if ($this->branch_id !== null) $data['branch_id'] = $this->branch_id;
        if ($this->biotime_id !== null) $data['biotime_id'] = $this->biotime_id;
        if ($this->is_active !== null) $data['is_active'] = $this->is_active;

        return $data;
    }

    public function hasUserUpdates(): bool
    {
        return $this->first_name !== null || $this->last_name !== null || $this->email !== null || $this->contact_number !== null || $this->is_active !== null;
    }

    public function hasDetailsUpdates(): bool
    {
        return $this->department_id !== null || $this->designation_id !== null || $this->basic_salary !== null || $this->branch_id !== null;
    }

    public function getUserData(): array
    {
        $data = [];
        if ($this->first_name !== null) $data['first_name'] = $this->first_name;
        if ($this->last_name !== null) $data['last_name'] = $this->last_name;
        if ($this->email !== null) $data['email'] = $this->email;
        if ($this->contact_number !== null) $data['contact_number'] = $this->contact_number;
        if ($this->is_active !== null) $data['is_active'] = $this->is_active ? 1 : 0;
        return $data;
    }

    public function getUserDetailsData(): array
    {
        $data = [];
        if ($this->department_id !== null) $data['department_id'] = $this->department_id;
        if ($this->designation_id !== null) $data['designation_id'] = $this->designation_id;
        if ($this->branch_id !== null) $data['branch_id'] = $this->branch_id;
        if ($this->basic_salary !== null) $data['basic_salary'] = $this->basic_salary;
        return $data;
    }
}
