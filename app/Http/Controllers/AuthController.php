<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public const EDUCATION = ['Fundamental incompleto', 'Fundamental completo', 'Médio incompleto', 'Médio completo', 'Superior incompleto', 'Superior completo', 'Pós-graduação', 'Mestrado', 'Doutorado', 'Outro'];

    public function register()
    {
        return view('auth.register', ['education' => self::EDUCATION]);
    }

    public function store(Request $r)
    {
        $r->merge(['email' => mb_strtolower(trim((string) $r->input('email'))), 'document' => strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string) $r->input('document'))), 'phone' => preg_replace('/\D/', '', (string) $r->input('phone')), 'postal_code' => preg_replace('/\D/', '', (string) $r->input('postal_code')), 'state' => strtoupper((string) $r->input('state'))]);
        $data = $r->validate([
            'name' => 'required|string|min:5|max:200', 'email' => 'required|email:rfc|max:255|unique:users,email', 'document' => 'required|string|min:5|max:25|unique:users,document', 'birth_date' => 'required|date|after:1900-01-01|before_or_equal:today',
            'address' => 'required|string|max:255', 'postal_code' => 'required|digits:8', 'city' => 'required|string|max:150', 'state' => ['required', Rule::in(explode(' ', 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'))], 'phone' => 'required|regex:/^\d{10,11}$/',
            'sex' => ['required', Rule::in(['Masculino', 'Feminino'])], 'nationality' => 'required|string|max:120', 'birthplace' => 'required|string|max:150', 'father_name' => 'nullable|string|max:200', 'mother_name' => 'required|string|max:200', 'education' => ['required', Rule::in(self::EDUCATION)], 'education_other' => 'nullable|required_if:education,Outro|string|max:150', 'church' => 'required|string|max:200', 'pastor' => 'required|string|max:200', 'church_role' => 'nullable|string|max:150', 'nucleus' => 'nullable|string|max:150', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], ['required' => 'Este campo é obrigatório.', 'unique' => 'Este dado já está cadastrado.', 'confirmed' => 'A confirmação da senha não confere.', 'email' => 'Informe um e-mail válido.', 'digits' => 'Confira a quantidade de números.']);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            if ($course = Course::first()) {
                Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id]);
            }

return $user;
        });
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Matrícula recebida. Aguarde a liberação do administrador para começar.');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt(['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password']], $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }
        $r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function password(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $r->user()->update(['password' => $data['password']]);
        $r->session()->regenerate();

        return back()->with('success','Senha atualizada.');
    }
}
