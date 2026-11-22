<div>
    <ul>
        <li><a href="{{ route('calculators.volume') }}">Litraż z wymiarów</a> (z zapisem w tym zbiorniku)</li>
        <li><a href="{{ route('calculators.dosage', ['tank' => $tankId]) }}">Dawkowanie z etykiety</a></li>
        <li><a href="{{ route('calculators.co2') }}">CO₂ z KH i pH</a></li>
        <li><a href="{{ route('calculators.stocking', ['tank' => $tankId]) }}">Cm ryby na litr</a> (z obsadą zbiornika)</li>
        <li><a href="{{ route('aquarium.redfield') }}">Kalkulator Redfielda</a></li>
    </ul>
</div>
