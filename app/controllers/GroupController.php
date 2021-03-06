<?php

/**
 * Handles Group interaction
 *
 * @author Björn Hjortsten
 */
class GroupController extends BaseController {
	
	public function __construct() {
		$this->beforeFilter('csrf', array('on' => array('post', 'delete', 'put')));
		$this->beforeFilter('auth');
		$this->beforeFilter('groupadmin', array('only' => array('edit', 'update', 'destroy')));
	}
	
	public function create() {
		return View::make('group/create')->with('title', 'Skapa ny grupp');
	}
	
	public function store() {
		$rules = array(
			'name' => 'required|unique:groups|max:255'
		);
		$messages = array(
			'required' => 'Fältet är obligatoriskt.',
			'unique' => 'En grupp med det angivna namnet finns redan.',
			'max' => 'Fältet får inte innehålla fler än :max tecken.'
		);
		
		$validator = Validator::make(Input::all(), $rules, $messages);
		if ($validator->fails()) {
			return Redirect::action('GroupController@create')->withErrors($validator)->withInput(Input::all());
		}
		
		$group = new Group();
		$group->name = Input::get('name');
		$group->description = Input::get('description');
		$group->createdBy()->associate(Auth::user());
		$group->created = new DateTime();
		$group->save();
		$group->users()->attach(Auth::user(), array('admin' => true));
		$group->save();
		
		return Redirect::to('/')->with('success', 'Gruppen "'.e(Input::get('name')).'" har skapats!');
	}
	
	public function index() {
		$groups = Group::all();
		return View::make('group/join')->with('title', 'Gå med i befintlig grupp')->with('groups', $groups);
	}
	
	public function show($id) {
		$group = Group::with('users')->where('id', '=', $id)->first();
		if ($group == null) {
			return App::abort(404, 'Gruppen finns inte');
		}
		$isMember = $group->users->contains(Auth::user()->id);
		$isAdmin = false;
		if ($isMember) {
			$isAdmin = (bool)$group->users->find(Auth::user()->id)->pivot->admin;
		}
		return View::make('group/show')->with('title', 'Visa grupp: '.e($group->name))->with('group', $group)
				->with('isAdmin', $isAdmin)->with('isMember', $isMember);
	}
	
	public function edit($id) {
		$group = Group::with('users')->where('id', '=', $id)->first();
		return View::make('group/edit')->with('title', 'Administrera grupp: '.e($group->name))->with('group', $group);
	}
	
	public function update($id) {
		$group = Group::with('users')->where('id', '=', $id)->first();
		
		$rules = array(
			'name' => 'required|max:255'
		);
		$messages = array(
			'required' => 'Fältet är obligatoriskt.',
			'unique' => 'En grupp med det angivna namnet finns redan.',
			'max' => 'Fältet får inte innehålla fler än :max tecken.'
		);
		
		$validator = Validator::make(Input::all(), $rules, $messages);
		if ($validator->fails()) {
			return Redirect::action('GroupController@edit')->withErrors($validator)->withInput(Input::all());
		}
		
		$group->name = Input::get('name');
		$group->description = Input::get('description');
		$group->updated = new DateTime();
		$group->updatedBy()->associate(Auth::user());
		$admins = 0;
		foreach (Input::get('admins') as $userId => $options) {
			$group->users->find($userId)->pivot->admin = isset($options['admin']);
			if (isset($options['admin'])) { $admins++; }
		}
		if ($admins === 0) {
			return Redirect::action('GroupController@edit', array($id))
					->with('danger', 'Det måste finnas minst en administratör!')->withInput(Input::all())
					->withErrors(array('users' => 'Det måste finnas minst en administratör!'));
		}
		
		$group->push();
		
		return Redirect::action('GroupController@show', array($id))->with('success', 'Ändringar sparade!');
	}
	
	public function destroy($id) {
		$group = Group::with('users')->where('id', '=', $id)->first();
		$group->delete();
		
		return Redirect::to('/')->with('success', 'Gruppen "'.e($group->name).'" togs bort!');
	}
	
	public function join($id) {
		$group = Group::with('users')->where('id', '=', $id)->first();
		if ($group == null) {
			return App::abort(404, 'Gruppen finns inte');
		}
		if ($group->users->contains(Auth::user()->id)) {
			return Redirect::action('GroupController@show', array($id))->with('info', 'Du är redan medlem!');
		}
		
		$group->users()->attach(Auth::user());
		$group->save();
		return Redirect::action('GroupController@show', array($id))->with('success', 'Du är nu medlem!');
	}
	
	public function leave($id) {
		$group = Group::with('users')->where('id', '=', $id)->first();
		if ($group == null) {
			return App::abort(404, 'Gruppen finns inte');
		}
		if (!$group->users->contains(Auth::user()->id)) {
			return Redirect::action('GroupController@show', array($id))->with('info', 'Du kan inte gå ur en grupp du inte är medlem i!');
		}
		
		$admins = DB::table('users')
				->join('groups_users', 'users.id', '=', 'groups_users.user_id')
				->where('groups_users.admin', '=', true)
				->where('groups_users.group_id', '=', $group->id)
				->lists('users.id');
		if (count($admins) < 2 && array_search(Auth::user()->id, $admins) !== false) {
			return Redirect::action('GroupController@show', array($id))
					->with('danger', 'Du kan inte gå ur en grupp när du är den sista kvarvarande administratören! Lämna över administratörsrollen till någon annan medlem eller ta bort gruppen helt.');
		}
		
		$group->users()->detach(Auth::user());
		$group->save();
		return Redirect::action('GroupController@show', array($id))->with('info', 'Du har nu gått ur gruppen!');
	}
}
