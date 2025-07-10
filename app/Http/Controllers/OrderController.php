<?php

namespace App\Http\Controllers;
use App\Http\Requests\CreateReviewRequest;
use App\Http\Requests\CreateComplaintRequest;
use App\Http\Requests\CreateNegotiationRequest;
use App\Helpers\ApiResponseHelper;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Models\OrderComplaint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Village;
use Illuminate\Support\Facades\DB;
use App\Models\OrderNegotiation;
use App\Helpers\RabbitMqHelper;
use App\Jobs\PublishMessageJob;
use Illuminate\Support\Facades\Storage;
use App\Models\OrderReview;

class OrderController
{
    public function CreateOrder(CreateOrderRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $rabbitMq = new RabbitMqHelper();
            $queue = $rabbitMq->getQueueByIndex(0);

            if (!$queue) {
                DB::rollBack();
                return ApiResponseHelper::respond(null, "Queue not found", 400);
            }
            $villageId = $validated['village_id'];
            $village = Village::with('district.regency.province')->find($villageId);
            if (!$village) {
                DB::rollBack();
                return ApiResponseHelper::respond(null, "villageId not found", 404);
            }

            $location = sprintf(
                "%s, %s, %s, %s",
                $village->name,
                optional($village->district)->name,
                optional(optional($village->district)->regency)->name,
                optional(optional(optional($village->district)->regency)->province)->name
            );

            $workDurationInMinutes = Carbon::parse($validated['work_start_time'])->diffInMinutes($validated['work_end_time']);
            $workDurationInHours = $workDurationInMinutes / 60;
            $travelDurationInHours = $validated['estimated_travel_time'] / 60;

            $totalCost = round(($workDurationInHours + $travelDurationInHours) * $validated['hourly_rate'], 2);

            $order = Order::create([
                'id' => Str::uuid(),
                'client_id' => Auth::id(),
                'freelancer_id' => $validated['freelancer_id'],
                'work_location' => $location,
                'work_start_time' => $validated['work_start_time'],
                'work_end_time' => $validated['work_end_time'],
                'estimated_travel_time' => $validated['estimated_travel_time'],
                'hourly_rate' => $validated['hourly_rate'],
                'total_cost' => $totalCost,
            ]);

            dispatch(new PublishMessageJob($queue, [
                'event' => 'order freelancer created',
                'data' => $order->toArray(),
                'status' => 201
            ]));

            DB::commit();

            return ApiResponseHelper::respond(
                $order->load(['freelancer', 'client']),
                'Order created successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                ['error' => $e->getMessage()],
                'Failed to create order',
                500
            );
        }
    }
    // negotiation
    public function CreateNegotiation(CreateNegotiationRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();

        try {
            OrderNegotiation::where('order_id', $validated['order_id'])->delete();

            $validated['id'] = (string) Str::uuid();
            $validated['total_cost'] = $validated['working_hours_duration'] * $validated['hourly_rate'];
            $validated['work_start_time'] = date('Y-m-d H:i:s', strtotime($validated['work_start_time']));
            $validated['work_end_time'] = date('Y-m-d H:i:s', strtotime($validated['work_end_time']));

            $negotiation = OrderNegotiation::create($validated);

            $rabbitMq = new RabbitMqHelper();
            $queue = $rabbitMq->getQueueByIndex(1);

            if ($queue) {
                dispatch(new PublishMessageJob($queue, [
                    'event' => 'order negotiation created',
                    'data' => $negotiation->toArray(),
                    'status' => 201
                ]));
            }

            DB::commit();

            return ApiResponseHelper::respond(
                $negotiation,
                "Negotiation created successfully.",
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                ['error' => $e->getMessage()],
                "Failed to create negotiation.",
                500
            );
        }
    }

    public function GetNegotiationByClientId($clientId)
    {
        $negotiation = OrderNegotiation::whereHas('order', function ($query) use ($clientId) {
            $query->where('client_id', $clientId);
        })
            ->latest()
            ->first();

        if ($negotiation) {
            return ApiResponseHelper::respond(
                $negotiation,
                "Negotiation data retrieved successfully.",
                200
            );
        }
        return ApiResponseHelper::respond(
            null,
            "Negotiation data not found.",
            404
        );
    }
    // reviews
    public function CreateReview(CreateReviewRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $reviewId = str::uuid();

            $existingReview = OrderReview::where('order_id', $validated['order_id'])->first();
            if ($existingReview) {
                return ApiResponseHelper::respond(
                    null,
                    'The review for this order is already exist.',
                    409
                );
            }

            $picturePath = null;

            if ($request->hasFile('review_file')) {
                $path = $request->file('review_file')->store('review_file', 'public');
                $picturePath = asset('storage/' . $path);
            }

            $rabbitmq = new RabbitMqHelper();
            $queue = $rabbitmq->getQueueByIndex(2);

            $review = OrderReview::create([
                'id' => $reviewId,
                'order_id' => $validated['order_id'],
                'rating_star' => $validated['rating_star'],
                'rating_text' => $validated['rating_text'] ?? null,
                'review_file_url' => $picturePath,
            ]);

            DB::commit();
            dispatch(new PublishMessageJob($queue, [
                'event' => 'Review Created Successfully',
                'data' => $review,
                'status' => 201,
            ]));

            return ApiResponseHelper::respond($review, "Review has been successfully created.", 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                null,
                "Failed to save the review." . $e->getMessage(),
                500
            );
        }
    }

    public function GetReviewsByClientId($clientId)
    {
        $reviews = OrderReview::whereHas('order', function ($q) use ($clientId) {
            $q->where('client_id', $clientId);
        })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($reviews->isNotEmpty()) {
            return ApiResponseHelper::respond(
                $reviews,
                "Reviews retrieved successfully.",
                200
            );
        }

        return ApiResponseHelper::respond(
            null,
            "No reviews found for this id.",
            404
        );
    }
    // complaints
    public function CreateComplaint(CreateComplaintRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();

        try {
            $rabbitMq = new RabbitMqHelper();
            $queue = $rabbitMq->getQueueByIndex(3);

            OrderComplaint::where('order_id', $validated['order_id'])->delete();
            $complaintId = Str::uuid();
            $path = null;
            if ($request->hasFile('evidence_file') && $request->file('evidence_file')->isValid()) {
                $extension = $request->file('evidence_file')->getClientOriginalExtension();
                $filename = $complaintId . '_evidence.' . $extension;

                $path = $request->file('evidence_file')->storeAs('complaint_evidence', $filename, 'public');
            }

            $orderComplaint = OrderComplaint::create([
                'id' => $complaintId,
                'order_id' => $validated['order_id'],
                'complaint_type' => $validated['complaint_type'],
                'description' => $validated['description'] ?? null,
                'evidence_url' => $path ? asset('storage/' . $path) : null,
                'contact_info' => $validated['contact_info'] ?? null,
            ]);

            dispatch(new PublishMessageJob($queue, [
                'event' => 'complaint created',
                'data' => $orderComplaint->toArray(),
                'status' => 201
            ]));

            DB::commit();

            return ApiResponseHelper::respond(
                $orderComplaint,
                "Create Complaint Success.",
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return ApiResponseHelper::respond(
                null,
                "Failed to create complaint: " . $e->getMessage(),
                500
            );
        }
    }
    public function GetComplaintsByClientId($clientId)
    {
        $complaints = OrderComplaint::whereHas('order', function ($q) use ($clientId) {
            $q->where('client_id', $clientId);
        })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($complaints->isNotEmpty()) {
            return ApiResponseHelper::respond(
                $complaints,
                "Complaints retrieved successfully.",
                200
            );
        }

        return ApiResponseHelper::respond(
            null,
            "No complaints found for this id.",
            404
        );
    }
}