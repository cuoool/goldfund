<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Customer;
use App\Plan;
use App\User;
use App\Deposit;
use Carbon\Carbon;

class DepositTest extends TestCase
{
	use DatabaseMigrations;
  	use DatabaseTransactions;

    /**
     * @test
     */
    public function customer_deposit_expect_pending_and_wait_for_payment_confirm_success()
    {
    	$backend_admin = factory(Customer::class)->create();
    	$plan = Plan::find(1);

    	$response = $this->actingAs($backend_admin, 'api')
    					->post('/api/deposit', [ 'plan_id' => $plan->id, 'amount' => $plan->min_deposit ]); 

    	$response->assertStatus(200)
    		->assertJson([
    			'cust_id' => $backend_admin->id,
    			'plan_id' => $plan->id,
    			'amount' => $plan->min_deposit,
    			'status' => 0,
                'issue_date' => null,
                'expire_date' => null,
    		]);
    }

    /**
     * @test
     */
    public function customer_allow_to_deposit_once_until_expiration()
    {
        $customer = factory(Customer::class)->create();
        $deposit = factory(Deposit::class)->create([ 'cust_id' => $customer->id, 'plan_id' => $this->plan->id ]);

        $response = $this->actingAs($customer, 'api')
                        ->post('/api/deposit', [ 'plan_id' => $this->plan->id, 'amount' => $this->plan->min_deposit ]); 

        $response->assertStatus(422);     
    }

    /**
     * @test
     */
    public function admin_approve_deposit()
    {
        $customer = factory(Customer::class)->create([
            'sponsor_id' => $this->cust_admin->id,
            'placement_id' => $this->cust_admin->id,
            'direction' => 'R',
        ]);
        $deposit = factory(Deposit::class)->create([ 'cust_id' => $customer->id, 'plan_id' => $this->plan->id ]);

        $this->assertTrue($customer->id === $deposit->cust_id);

        $response = $this->actingAs($this->backend_admin, 'api_admin')
                        ->post('api/admin/deposit/' . $deposit->id . '/approve');

        $response->assertStatus(200);
        
        $deposit_after_approved = Deposit::find($deposit->id);

        $this->assertTrue($deposit_after_approved->status == 1); //approved
        $this->assertTrue($deposit_after_approved->issue_date !== null);
        $this->assertTrue(
            $deposit_after_approved->expire_date == Carbon::createFromFormat('Y-m-d H:i:s', $deposit_after_approved->issue_date)->addDays($this->plan->duration)
        );
    }
}
