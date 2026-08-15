<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Register</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>
    <x-app-layout>
    <div class="min-h-screen py-12 bg-gray-100"> <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-extrabold text-gray-800 mb-6 border-r-4 border-indigo-500 pr-4">
                        طلبات التوثيق المعلقة
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-gray-200 text-gray-700 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-right">User Name</th>
                                    <th class="py-3 px-6 text-right">Role</th>
                                    <th class="py-3 px-6 text-center">Rent_Status</th>
                                    <th class="py-3 px-6 text-center">Response</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                                @php $pendingUsers; @endphp

                                @forelse($pendingUsers as $user)
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition duration-200">
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <span class="font-bold text-black-900 text-base">{{ $user->first_name  }}<span>_</span> {{$user->last_name}}</span>
                                    </td>

                                    <td class="py-4 px-6 text-right">
                                        <span class="px-3 py-2 rounded-full text-sm {{ $user->role == 'landlord' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                            {{ $user->role == 'landlord' ? 'صاحب شقة' : 'مستأجر' }}
                                        </span>
                                    </td>

                                    <td class="py-4 px-6 text-center">
                                        <span class="bg-yellow-100 text-yellow-800 py-1 px-3 rounded-full text-xs font-semibold">
                                            قيد الانتظار
                                        </span>
                                    </td>

                                    <td class="py-4 px-6 text-center">
                                        <div class="flex item-center justify-center gap-3">
                                            <form action="{{ route('admin.updateStatus') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="verified_status" value="approved">
                                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                                <button class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 transform hover:scale-105">
                                                    قبول
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.updateStatus') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="verified_status" value="rejected">
                                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                                <button class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 transform hover:scale-105">
                                                    رفض
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="py-10 text-center text-gray-500 italic">
                                        لا توجد طلبات معلقة في الوقت الحالي.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
</body>
</html>
{{--
1- Method
2- Action
3- csrf
 --}}
