<?php

/**
 * Handles bookings
 *
 * @author Björn Hjortsten
 */
class BookingController extends BaseController {
	
	public function __construct() {
		$this->beforeFilter('csrf', array('on' => array('post', 'delete', 'put')));
		$this->beforeFilter('auth');
		$this->beforeFilter('groupmember');
	}
	
	public function create($vehicleId) {
		$vehicle = Vehicle::find($vehicleId);
		if ($vehicle == null) {
			return App::abort(404, 'Fordonet finns inte');
		}
		return View::make('booking/create')->with('vehicle', $vehicle)->with('title', 'Boka '.$vehicle->name);
	}
	
	public function store($vehicleId) {
		$vehicle = Vehicle::find($vehicleId);
		if ($vehicle == null) {
			return App::abort(404, 'Fordonet finns inte');
		}
		
		$rules = array(
			'start.date' => 'required|date_format:Y-m-d|after:'.date('Y-m-d', strtotime('-1 day')),
			'end.date' => 'required|date_format:Y-m-d|end_date:start.date',
			'start.time' => array('regex:/([01]?[0-9]|2[0-3]):[0-5][0-9]/'),
			'end.time' => array('regex:/([01]?[0-9]|2[0-3]):[0-5][0-9]/')
		);
		$messages = array(
			'required' => 'Fältet är obligatoriskt.',
			'date_format' => 'Datumet måste vara på formatet ÅÅÅÅ-MM-DD.',
			'after' => 'Datumet måste vara senare än :date.',
			'end_date' => 'Slutdatumet får inte vara före startdatumet.',
			'regex' => 'Klockslaget måste vara på formatet HH:MM.'
		);
		$validator = Validator::make(Input::all(), $rules, $messages);
		$validator->sometimes(array('start.time', 'end.time'), 'required', function($input) {
			return !isset($input->wholeday);
		});
		if ($validator->fails()) {
			return Redirect::action('BookingController@create', array($vehicleId))
					->withErrors($validator)->withInput(Input::all());
		}
		if (Input::has('wholeday')) {
			$startDate = new DateTime(Input::get('start.date').' 00:00:00');
			$endDate = new DateTime(Input::get('end.date').' 23:59:59');
		} else {
			$startDate = new DateTime(Input::get('start.date').' '.Input::get('start.time'));
			$endDate = new DateTime(Input::get('end.date').' '.Input::get('end.time'));
		}
		if ($startDate > $endDate) {
			return Redirect::action('BookingController@create', array($vehicleId))
					->withErrors(array('end.time' => 'Sluttiden får inte vara före starttiden.'))
					->withInput(Input::all());
		}
		
		$conflictingBookings = Booking::
				where('vehicle_id', '=', $vehicle->id)
				->where(function($query) use ($startDate, $endDate) {
					$query->where('start', '<', $endDate)
						->where('end', '>', $startDate);
				})
				->orderBy('start', 'asc')
				->get();
		if (!$conflictingBookings->isEmpty()) {
			return Redirect::action('BookingController@create', array($vehicleId))
					->withErrors(array('start.date' => true, 'end.date' => true))
					->withInput(Input::all())
					->with('conflictingBookings', $conflictingBookings)
					->with('danger', 'Bokningen krockar med en eller flera andra befintliga bokningar.');
		}
		
		$booking = new Booking();
		$booking->user()->associate(Auth::user());
		$booking->vehicle()->associate($vehicle);
		$booking->start = $startDate;
		$booking->end = $endDate;
		if (Input::has('comment')) {
			$booking->comment = Input::get('comment');
		}
		$booking->save();
		
		return Redirect::action('BookingController@show', array($booking->id))->with('success', 'Fordon bokat!');
	}
	
	public function show($bookingId) {
		$booking = Booking::find($bookingId);
		if ($booking == null) {
			return App::abort(404, 'Bokningen finns inte');
		}
		$isAdmin = $booking->user->id == Auth::user()->id || 
				($booking->vehicle->group->users->contains(Auth::user()->id) && 
				$booking->vehicle->group->users->find(Auth::user()->id)->pivot->admin);
		return View::make('booking/show')->with('booking', $booking)->with('isAdmin', $isAdmin)
				->with('title', 'Bokning av '.$booking->vehicle->name);
	}
}
