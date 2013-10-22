@extends('layout')

@section('content')
@include('navbar')
<div class="container">
	<div class="page-header">
		<h1>{{{ $group->name }}}</h1>
	</div>
	<p>{{ nl2br(e($group->description)) }}</p>
	<div class="row">
		<div class="col-md-6">
			<dl>
				<dt>Skapad</dt>
				<dd>{{ date('Y-m-d H:i', strtotime($group->created)) }}</dd>
			</dl>
		</div>
		<div class="col-md-6">
			<dl>
				<dt>Skapad av</dt>
				<dd>
					@if ($group->users->contains(Auth::user()->id))
						{{{ $group->createdBy()->name }}}
					@else
						{{{ $group->createdBy()->alias }}}
					@endif
				</dd>
			</dl>
		</div>
	</div>
	@if ($group->updated)
	<div class="row">
		<div class="col-md-6">
			<dl>
				<dt>Uppdaterad</dt>
				<dd>{{ date('Y-m-d H:i', strtotime($group->updated)) }}</dd>
			</dl>
		</div>
		<div class="col-md-6">
			<dl>
				<dt>Uppdaterad av</dt>
				<dd>
					@if ($group->users->contains(Auth::user()->id))
						{{{ $group->updatedBy()->name }}}
					@else
						{{{ $group->updatedBy()->alias }}}
					@endif
				</dd>
			</dl>
		</div>
	</div>
	@endif
	@if ($group->users->contains(Auth::user()->id))
		<h2>Medlemmar</h2>
		<table class="table table-hover">
			<tr>
				<th>Namn</th>
				<th>E-postadress</th>
				<th>Administratör</th>
			</tr>
			@foreach ($group->users as $user)
			<tr>
				<td>{{{ $user->name }}}</td>
				<td>{{{ $user->email }}}</td>
				<td>@if ($user->pivot->admin) <span class="glyphicon glyphicon-ok-sign"></span> @endif</td>
			</tr>
			@endforeach
		</table>
	@else
		<dl>
			<dt>Medlemmar</dt>
			<dd>{{ count($group->users) }}st.</dd>
		</dl>
	@endif
</div>
@stop
