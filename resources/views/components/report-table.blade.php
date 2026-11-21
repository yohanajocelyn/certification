{{-- Report rows: rentals (by start date) and extensions (by date made), with a totals row. --}}
@props([
    'rows',
    'totals',
    'bucketHeading',
])

<x-table>
    <x-slot:head>
        <th scope="col">{{ $bucketHeading }}</th>
        <th scope="col" class="text-right">Jumlah Sewa</th>
        <th scope="col" class="text-right">Pendapatan Sewa</th>
        <th scope="col" class="text-right">Perpanjangan</th>
        <th scope="col" class="text-right">Pendapatan Perpanjangan</th>
        <th scope="col" class="text-right">Total Pendapatan</th>
    </x-slot:head>

    @foreach ($rows as $row)
        <tr @class(['text-primary-500' => $row['rentals'] === 0 && $row['extensions'] === 0])>
            <td data-label="" class="font-semibold text-primary-900 wrap-anywhere">{{ $row['label'] }}</td>
            <td data-label="Jumlah Sewa" class="text-right">{{ $row['rentals'] }}</td>
            <td data-label="Pendapatan Sewa" class="text-right"><x-rupiah :amount="$row['rental_revenue']" /></td>
            <td data-label="Perpanjangan" class="text-right">{{ $row['extensions'] }}</td>
            <td data-label="Pendapatan Perpanjangan" class="text-right"><x-rupiah :amount="$row['extension_revenue']" /></td>
            <td data-label="Total Pendapatan" class="text-right font-bold text-primary-900"><x-rupiah :amount="$row['total_revenue']" /></td>
        </tr>
    @endforeach

    <x-slot:foot>
        <tr>
            <td data-label="">{{ $totals['label'] }}</td>
            <td data-label="Jumlah Sewa" class="text-right">{{ $totals['rentals'] }}</td>
            <td data-label="Pendapatan Sewa" class="text-right"><x-rupiah :amount="$totals['rental_revenue']" /></td>
            <td data-label="Perpanjangan" class="text-right">{{ $totals['extensions'] }}</td>
            <td data-label="Pendapatan Perpanjangan" class="text-right"><x-rupiah :amount="$totals['extension_revenue']" /></td>
            <td data-label="Total Pendapatan" class="text-right"><x-rupiah :amount="$totals['total_revenue']" /></td>
        </tr>
    </x-slot:foot>
</x-table>

<p class="text-sm text-primary-600 mt-3">Sewa dihitung dari tanggal mulai, perpanjangan dari tanggal dibuat.</p>
